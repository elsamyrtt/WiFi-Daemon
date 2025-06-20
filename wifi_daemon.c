#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <unistd.h>
#include <signal.h>
#include <sys/types.h>
#include <sys/stat.h>
#include <fcntl.h>
#include <syslog.h>
#include <time.h>
#include <json-c/json.h>
#include <errno.h>

#define CONFIG_FILE "/etc/wifi_daemon.json"
#define BATTERY_PATH "/sys/class/power_supply/BAT0/capacity"
#define TEMP_PATH "/sys/class/thermal/thermal_zone0/temp"
#define MAX_BUFFER 1024
#define MAX_COMMAND 512

typedef struct {
    char ssid[64];
    char password[64];
    char static_ip[32];
    char gateway[32];
    char dns[32];
    int check_interval;
    int min_battery;
    int max_temp;
    char power_mode[16];
    int active_days[7];
    int start_hour;
    int end_hour;
    int disable_duration;
    time_t disable_until;
} wifi_config_t;

typedef struct {
    int connected;
    int battery_level;
    int temperature;
    time_t last_check;
    int reconnect_attempts;
} daemon_state_t;

static volatile int running = 1;
static wifi_config_t config;
static daemon_state_t state;

void signal_handler(int sig) {
    if (sig == SIGTERM || sig == SIGINT) {
        running = 0;
        syslog(LOG_INFO, "Received signal %d, shutting down", sig);
    }
}

void load_default_config() {
    strcpy(config.ssid, "default_wifi");
    strcpy(config.password, "default_password");
    strcpy(config.static_ip, "192.168.1.100");
    strcpy(config.gateway, "192.168.1.1");
    strcpy(config.dns, "8.8.8.8");
    config.check_interval = 30;
    config.min_battery = 20;
    config.max_temp = 80000;
    strcpy(config.power_mode, "normal");
    for (int i = 0; i < 7; i++) config.active_days[i] = 1;
    config.start_hour = 0;
    config.end_hour = 23;
    config.disable_duration = 0;
    config.disable_until = 0;
}

int load_config() {
    FILE *file = fopen(CONFIG_FILE, "r");
    if (!file) {
        syslog(LOG_WARNING, "Config file not found, using defaults");
        load_default_config();
        return 0;
    }

    fseek(file, 0, SEEK_END);
    long length = ftell(file);
    fseek(file, 0, SEEK_SET);
    
    char *buffer = malloc(length + 1);
    if (!buffer) {
        fclose(file);
        return -1;
    }
    
    fread(buffer, 1, length, file);
    buffer[length] = '\0';
    fclose(file);

    json_object *root = json_tokener_parse(buffer);
    free(buffer);
    
    if (!root) {
        syslog(LOG_ERR, "Invalid JSON in config file");
        load_default_config();
        return -1;
    }

    json_object *obj;
    if (json_object_object_get_ex(root, "ssid", &obj))
        strcpy(config.ssid, json_object_get_string(obj));
    if (json_object_object_get_ex(root, "password", &obj))
        strcpy(config.password, json_object_get_string(obj));
    if (json_object_object_get_ex(root, "static_ip", &obj))
        strcpy(config.static_ip, json_object_get_string(obj));
    if (json_object_object_get_ex(root, "gateway", &obj))
        strcpy(config.gateway, json_object_get_string(obj));
    if (json_object_object_get_ex(root, "dns", &obj))
        strcpy(config.dns, json_object_get_string(obj));
    if (json_object_object_get_ex(root, "check_interval", &obj))
        config.check_interval = json_object_get_int(obj);
    if (json_object_object_get_ex(root, "min_battery", &obj))
        config.min_battery = json_object_get_int(obj);
    if (json_object_object_get_ex(root, "max_temp", &obj))
        config.max_temp = json_object_get_int(obj);
    if (json_object_object_get_ex(root, "power_mode", &obj))
        strcpy(config.power_mode, json_object_get_string(obj));
    if (json_object_object_get_ex(root, "start_hour", &obj))
        config.start_hour = json_object_get_int(obj);
    if (json_object_object_get_ex(root, "end_hour", &obj))
        config.end_hour = json_object_get_int(obj);
    if (json_object_object_get_ex(root, "disable_duration", &obj))
        config.disable_duration = json_object_get_int(obj);
    if (json_object_object_get_ex(root, "disable_until", &obj))
        config.disable_until = json_object_get_int64(obj);

    json_object *days;
    if (json_object_object_get_ex(root, "active_days", &days)) {
        int array_length = json_object_array_length(days);
        for (int i = 0; i < 7 && i < array_length; i++) {
            json_object *day = json_object_array_get_idx(days, i);
            config.active_days[i] = json_object_get_int(day);
        }
    }

    json_object_put(root);
    return 0;
}

int get_battery_level() {
    FILE *file = fopen(BATTERY_PATH, "r");
    if (!file) return 100;
    
    int level;
    if (fscanf(file, "%d", &level) != 1) level = 100;
    fclose(file);
    return level;
}

int get_temperature() {
    FILE *file = fopen(TEMP_PATH, "r");
    if (!file) return 0;
    
    int temp;
    if (fscanf(file, "%d", &temp) != 1) temp = 0;
    fclose(file);
    return temp;
}

int check_internet() {
    int result = system("ping -c 1 -W 5 8.8.8.8 > /dev/null 2>&1");
    return (result == 0);
}

int is_active_time() {
    time_t now = time(NULL);
    struct tm *tm_info = localtime(&now);
    
    if (config.disable_until > 0 && now < config.disable_until) {
        return 0;
    }
    
    if (!config.active_days[tm_info->tm_wday]) {
        return 0;
    }
    
    if (tm_info->tm_hour < config.start_hour || tm_info->tm_hour > config.end_hour) {
        return 0;
    }
    
    return 1;
}

int should_reconnect() {
    if (!is_active_time()) {
        return 0;
    }
    
    state.battery_level = get_battery_level();
    state.temperature = get_temperature();
    
    if (state.battery_level < config.min_battery) {
        syslog(LOG_INFO, "Battery too low: %d%%", state.battery_level);
        return 0;
    }
    
    if (state.temperature > config.max_temp) {
        syslog(LOG_INFO, "Temperature too high: %d", state.temperature);
        return 0;
    }
    
    return 1;
}

void reconnect_wifi() {
    char command[MAX_COMMAND];
    int result;
    
    syslog(LOG_INFO, "Attempting to reconnect to WiFi: %s", config.ssid);
    
    snprintf(command, sizeof(command), "nmcli radio wifi on");
    system(command);
    
    snprintf(command, sizeof(command), 
             "nmcli dev wifi connect \"%s\" password \"%s\"", 
             config.ssid, config.password);
    result = system(command);
    
    if (result != 0) {
        syslog(LOG_ERR, "Failed to connect to WiFi");
        state.reconnect_attempts++;
        return;
    }
    
    sleep(5);
    
    snprintf(command, sizeof(command),
             "nmcli con mod \"%s\" ipv4.addresses %s ipv4.gateway %s ipv4.dns %s ipv4.method manual",
             config.ssid, config.static_ip, config.gateway, config.dns);
    system(command);
    
    snprintf(command, sizeof(command), "nmcli con up \"%s\"", config.ssid);
    system(command);
    
    sleep(3);
    
    FILE *ip_check = popen("ip route get 8.8.8.8 | head -n1 | awk '{print $7}'", "r");
    if (ip_check) {
        char current_ip[32];
        if (fgets(current_ip, sizeof(current_ip), ip_check)) {
            current_ip[strcspn(current_ip, "\n")] = 0;
            char expected_ip[32];
            strcpy(expected_ip, config.static_ip);
            char *slash = strchr(expected_ip, '/');
            if (slash) *slash = '\0';
            
            if (strcmp(current_ip, expected_ip) == 0) {
                syslog(LOG_INFO, "Successfully reconnected with IP: %s", current_ip);
                state.reconnect_attempts = 0;
            } else {
                syslog(LOG_WARNING, "IP mismatch: got %s, expected %s", current_ip, expected_ip);
            }
        }
        pclose(ip_check);
    }
}

void daemonize() {
    pid_t pid = fork();
    
    if (pid < 0) {
        exit(EXIT_FAILURE);
    }
    
    if (pid > 0) {
        exit(EXIT_SUCCESS);
    }
    
    if (setsid() < 0) {
        exit(EXIT_FAILURE);
    }
    
    signal(SIGCHLD, SIG_IGN);
    signal(SIGHUP, SIG_IGN);
    
    pid = fork();
    
    if (pid < 0) {
        exit(EXIT_FAILURE);
    }
    
    if (pid > 0) {
        exit(EXIT_SUCCESS);
    }
    
    umask(0);
    chdir("/");
    
    for (int fd = sysconf(_SC_OPEN_MAX); fd >= 0; fd--) {
        close(fd);
    }
    
    open("/dev/null", O_RDWR);
    dup(0);
    dup(0);
}

int main() {
    daemonize();
    
    openlog("wifi_daemon", LOG_PID, LOG_DAEMON);
    
    signal(SIGTERM, signal_handler);
    signal(SIGINT, signal_handler);
    
    load_config();
    
    memset(&state, 0, sizeof(state));
    
    syslog(LOG_INFO, "WiFi daemon started");
    
    while (running) {
        load_config();
        
        state.connected = check_internet();
        state.last_check = time(NULL);
        
        if (!state.connected) {
            syslog(LOG_INFO, "Internet connection lost");
            
            if (should_reconnect()) {
                reconnect_wifi();
                
                sleep(10);
                state.connected = check_internet();
                
                if (state.connected) {
                    syslog(LOG_INFO, "Internet connection restored");
                }
            }
        }
        
        int sleep_interval = config.check_interval;
        if (strcmp(config.power_mode, "eco") == 0) {
            sleep_interval *= 2;
        } else if (strcmp(config.power_mode, "aggressive") == 0) {
            sleep_interval /= 2;
            if (sleep_interval < 5) sleep_interval = 5;
        }
        
        sleep(sleep_interval);
    }
    
    syslog(LOG_INFO, "WiFi daemon stopped");
    closelog();
    
    return 0;
}