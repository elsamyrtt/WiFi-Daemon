<?php
define('CONFIG_FILE', '/etc/wifi_daemon.json');

function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return [
            'ssid' => 'default_wifi',
            'password' => 'default_password',
            'static_ip' => '192.168.1.100',
            'gateway' => '192.168.1.1',
            'dns' => '8.8.8.8',
            'check_interval' => 30,
            'min_battery' => 20,
            'max_temp' => 80000,
            'power_mode' => 'normal',
            'active_days' => [1, 1, 1, 1, 1, 1, 1],
            'start_hour' => 0,
            'end_hour' => 23,
            'disable_duration' => 0,
            'disable_until' => 0
        ];
    }
    
    $content = file_get_contents(CONFIG_FILE);
    return json_decode($content, true) ?: [];
}

function saveConfig($config) {
    $json = json_encode($config, JSON_PRETTY_PRINT);
    return file_put_contents(CONFIG_FILE, $json, LOCK_EX) !== false;
}

function restartDaemon() {
    exec('systemctl restart wifi_daemon 2>&1', $output, $return_code);
    return $return_code === 0;
}

$config = loadConfig();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_power_mode':
                if (in_array($_POST['power_mode'], ['normal', 'eco', 'aggressive'])) {
                    $config['power_mode'] = $_POST['power_mode'];
                    if (saveConfig($config)) {
                        $message = 'Power mode updated successfully';
                        restartDaemon();
                    } else {
                        $error = 'Failed to save configuration';
                    }
                }
                break;
                
            case 'disable_daemon':
                $duration = intval($_POST['disable_duration']);
                if ($duration > 0) {
                    $config['disable_until'] = time() + ($duration * 60);
                    $config['disable_duration'] = $duration;
                    if (saveConfig($config)) {
                        $message = "Daemon disabled for {$duration} minutes";
                        restartDaemon();
                    } else {
                        $error = 'Failed to save configuration';
                    }
                }
                break;
                
            case 'update_schedule':
                $config['start_hour'] = intval($_POST['start_hour']);
                $config['end_hour'] = intval($_POST['end_hour']);
                $config['active_days'] = [];
                for ($i = 0; $i < 7; $i++) {
                    $config['active_days'][$i] = isset($_POST['day_' . $i]) ? 1 : 0;
                }
                if (saveConfig($config)) {
                    $message = 'Schedule updated successfully';
                    restartDaemon();
                } else {
                    $error = 'Failed to save configuration';
                }
                break;
                
            case 'update_network':
                $config['ssid'] = $_POST['ssid'];
                $config['password'] = $_POST['password'];
                $config['static_ip'] = $_POST['static_ip'];
                $config['gateway'] = $_POST['gateway'];
                $config['dns'] = $_POST['dns'];
                if (saveConfig($config)) {
                    $message = 'Network settings updated successfully';
                    restartDaemon();
                } else {
                    $error = 'Failed to save configuration';
                }
                break;
                
            case 'update_system':
                $config['check_interval'] = intval($_POST['check_interval']);
                $config['min_battery'] = intval($_POST['min_battery']);
                $config['max_temp'] = intval($_POST['max_temp']);
                if (saveConfig($config)) {
                    $message = 'System settings updated successfully';
                    restartDaemon();
                } else {
                    $error = 'Failed to save configuration';
                }
                break;
        }
    }
}

$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Daemon Control Panel</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .section h3 { margin-top: 0; color: #333; }
        .form-group { margin: 10px 0; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px 0; }
        button:hover { background-color: #005a87; }
        .message { background-color: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .checkbox-group { display: flex; gap: 10px; flex-wrap: wrap; }
        .checkbox-item { display: flex; align-items: center; }
        .checkbox-item input { width: auto; margin-right: 5px; }
        .status { padding: 10px; background-color: #e9ecef; border-radius: 4px; margin: 10px 0; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        @media (max-width: 600px) { .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <h1>WiFi Daemon Control Panel</h1>
        
        <?php if ($message): ?>
            <div class="message"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="status">
            <h3>Current Status</h3>
            <p><strong>Power Mode:</strong> <?php echo htmlspecialchars($config['power_mode']); ?></p>
            <p><strong>Disabled Until:</strong> 
                <?php 
                if ($config['disable_until'] > time()) {
                    echo date('Y-m-d H:i:s', $config['disable_until']);
                } else {
                    echo 'Active';
                }
                ?>
            </p>
            <p><strong>Active Hours:</strong> <?php echo $config['start_hour']; ?>:00 - <?php echo $config['end_hour']; ?>:59</p>
        </div>
        
        <div class="grid">
            <div class="section">
                <h3>Power Mode</h3>
                <form method="post">
                    <input type="hidden" name="action" value="update_power_mode">
                    <div class="form-group">
                        <label for="power_mode">Power Mode:</label>
                        <select name="power_mode" id="power_mode">
                            <option value="normal" <?php echo $config['power_mode'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="eco" <?php echo $config['power_mode'] === 'eco' ? 'selected' : ''; ?>>Eco</option>
                            <option value="aggressive" <?php echo $config['power_mode'] === 'aggressive' ? 'selected' : ''; ?>>Aggressive</option>
                        </select>
                    </div>
                    <button type="submit">Update Power Mode</button>
                </form>
            </div>
            
            <div class="section">
                <h3>Temporary Disable</h3>
                <form method="post">
                    <input type="hidden" name="action" value="disable_daemon">
                    <div class="form-group">
                        <label for="disable_duration">Disable for (minutes):</label>
                        <input type="number" name="disable_duration" id="disable_duration" min="1" max="1440" value="60">
                    </div>
                    <button type="submit">Disable Daemon</button>
                </form>
            </div>
        </div>
        
        <div class="section">
            <h3>Schedule Settings</h3>
            <form method="post">
                <input type="hidden" name="action" value="update_schedule">
                <div class="grid">
                    <div class="form-group">
                        <label for="start_hour">Start Hour (0-23):</label>
                        <input type="number" name="start_hour" id="start_hour" min="0" max="23" value="<?php echo $config['start_hour']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="end_hour">End Hour (0-23):</label>
                        <input type="number" name="end_hour" id="end_hour" min="0" max="23" value="<?php echo $config['end_hour']; ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Active Days:</label>
                    <div class="checkbox-group">
                        <?php for ($i = 0; $i < 7; $i++): ?>
                            <div class="checkbox-item">
                                <input type="checkbox" name="day_<?php echo $i; ?>" id="day_<?php echo $i; ?>" 
                                       <?php echo $config['active_days'][$i] ? 'checked' : ''; ?>>
                                <label for="day_<?php echo $i; ?>"><?php echo $days[$i]; ?></label>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
                <button type="submit">Update Schedule</button>
            </form>
        </div>
        
        <div class="section">
            <h3>Network Settings</h3>
            <form method="post">
                <input type="hidden" name="action" value="update_network">
                <div class="grid">
                    <div class="form-group">
                        <label for="ssid">WiFi SSID:</label>
                        <input type="text" name="ssid" id="ssid" value="<?php echo htmlspecialchars($config['ssid']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="password">WiFi Password:</label>
                        <input type="password" name="password" id="password" value="<?php echo htmlspecialchars($config['password']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="static_ip">Static IP:</label>
                        <input type="text" name="static_ip" id="static_ip" value="<?php echo htmlspecialchars($config['static_ip']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="gateway">Gateway:</label>
                        <input type="text" name="gateway" id="gateway" value="<?php echo htmlspecialchars($config['gateway']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="dns">DNS Server:</label>
                        <input type="text" name="dns" id="dns" value="<?php echo htmlspecialchars($config['dns']); ?>">
                    </div>
                </div>
                <button type="submit">Update Network Settings</button>
            </form>
        </div>
        
        <div class="section">
            <h3>System Settings</h3>
            <form method="post">
                <input type="hidden" name="action" value="update_system">
                <div class="grid">
                    <div class="form-group">
                        <label for="check_interval">Check Interval (seconds):</label>
                        <input type="number" name="check_interval" id="check_interval" min="5" max="300" value="<?php echo $config['check_interval']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="min_battery">Minimum Battery (%):</label>
                        <input type="number" name="min_battery" id="min_battery" min="0" max="100" value="<?php echo $config['min_battery']; ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_temp">Maximum Temperature (milli°C):</label>
                        <input type="number" name="max_temp" id="max_temp" min="0" max="100000" value="<?php echo $config['max_temp']; ?>">
                    </div>
                </div>
                <button type="submit">Update System Settings</button>
            </form>
        </div>
        
        <div class="section">
            <h3>Current Configuration (JSON)</h3>
            <textarea rows="15" readonly><?php echo json_encode($config, JSON_PRETTY_PRINT); ?></textarea>
        </div>
    </div>
</body>
</html>
