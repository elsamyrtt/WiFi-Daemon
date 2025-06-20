# WiFi Daemon - Gestor Automático de Conexión WiFi

Un demonio en C para Debian 12 Server que gestiona automáticamente las conexiones WiFi con configuración por JSON y control web.

## Características

- **Demonio persistente**: Se ejecuta en segundo plano como servicio systemd
- **Monitoreo continuo**: Verifica la conexión a internet cada X segundos
- **Reconexión automática**: Se reconecta automáticamente cuando se pierde la conexión
- **IP estática**: Configura automáticamente la IP estática deseada
- **Gestión de energía**: Diferentes modos de energía (normal, eco, aggressive)
- **Protección térmica**: No reconecta si la temperatura es muy alta
- **Gestión de batería**: No reconecta si la batería está muy baja
- **Horarios activos**: Solo opera en días y horas específicas
- **Control web**: Interfaz PHP para configuración remota
- **Configuración JSON**: Toda la configuración en `/etc/wifi_daemon.json`
- **Logging**: Registra todo en syslog

## Requisitos del Sistema

- Debian 12 Server
- gcc
- libjson-c-dev
- network-manager
- Apache2 o nginx (para interfaz web)
- PHP (para interfaz web)

## Instalación Rápida

```bash
# Instalar dependencias
sudo apt update
sudo apt install -y gcc libjson-c-dev network-manager apache2 php

# Compilar
make

# Instalar con script automático
chmod +x install.sh
sudo ./install.sh
```

## Instalación Manual

```bash
# Compilar el demonio
gcc -o wifi_daemon wifi_daemon.c -ljson-c

# Copiar binario
sudo cp wifi_daemon /usr/local/bin/
sudo chmod +x /usr/local/bin/wifi_daemon

# Crear servicio systemd
sudo cp wifi_daemon.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable wifi_daemon

# Configurar interfaz web
sudo cp wifi_control.php /var/www/html/
sudo chown www-data:www-data /var/www/html/wifi_control.php

# Permitir al usuario web reiniciar el servicio
echo "www-data ALL=(ALL) NOPASSWD: /bin/systemctl restart wifi_daemon" | sudo tee /etc/sudoers.d/wifi_daemon
sudo chmod 440 /etc/sudoers.d/wifi_daemon
```

## Configuración

Editar `/etc/wifi_daemon.json`:

```json
{
    "ssid": "tu_red_wifi",
    "password": "tu_contraseña",
    "static_ip": "192.168.1.100/24",
    "gateway": "192.168.1.1",
    "dns": "8.8.8.8",
    "check_interval": 30,
    "min_battery": 20,
    "max_temp": 80000,
    "power_mode": "normal",
    "active_days": [1, 1, 1, 1, 1, 1, 1],
    "start_hour": 0,
    "end_hour": 23,
    "disable_duration": 0,
    "disable_until": 0
}
```

### Parámetros de Configuración

- **ssid**: Nombre de la red WiFi
- **password**: Contraseña de la red WiFi
- **static_ip**: IP estática deseada (formato CIDR)
- **gateway**: Gateway de la red
- **dns**: Servidor DNS
- **check_interval**: Intervalo de verificación en segundos
- **min_battery**: Nivel mínimo de batería para operar (%)
- **max_temp**: Temperatura máxima en mili-Celsius (80000 = 80°C)
- **power_mode**: Modo de energía (normal, eco, aggressive)
- **active_days**: Días activos [Dom, Lun, Mar, Mie, Jue, Vie, Sab]
- **start_hour**: Hora de inicio (0-23)
- **end_hour**: Hora de fin (0-23)
- **disable_duration**: Duración de desactivación en minutos
- **disable_until**: Timestamp hasta cuando está desactivado

## Modos de Energía

- **normal**: Intervalo de verificación estándar
- **eco**: Intervalo de verificación duplicado (ahorra energía)
- **aggressive**: Intervalo de verificación reducido a la mitad

## Comandos de Control

```bash
# Iniciar el demonio
sudo systemctl start wifi_daemon

# Detener el demonio
sudo systemctl stop wifi_daemon

# Reiniciar el demonio
sudo systemctl restart wifi_daemon

# Ver estado
sudo systemctl status wifi_daemon

# Ver logs en tiempo real
sudo journalctl -u wifi_daemon -f

# Habilitar inicio automático
sudo systemctl enable wifi_daemon

# Deshabilitar inicio automático
sudo systemctl disable wifi_daemon
```

## Uso con Makefile

```bash
# Compilar
make

# Instalar
make install

# Ver estado
make service

# Ver logs
make logs

# Iniciar/parar/reiniciar
make start
make stop
make restart

# Habilitar/deshabilitar
make enable
make disable
```

## Interfaz Web

Accede a `http://tu-servidor-ip/wifi_control.php` para:

- Cambiar el modo de energía
- Desactivar temporalmente el demonio
- Configurar horarios activos
- Modificar configuración de red
- Ajustar parámetros del sistema
- Ver la configuración actual

## Monitoreo

El demonio registra toda su actividad en syslog. Para ver los logs:

```bash
# Logs en tiempo real
sudo journalctl -u wifi_daemon -f

# Logs recientes
sudo journalctl -u wifi_daemon -n 50

# Logs por fecha
sudo journalctl -u wifi_daemon --since "2025-01-01"
```

## Troubleshooting

### El demonio no inicia
```bash
# Verificar sintaxis JSON
sudo cat /etc/wifi_daemon.json | python3 -m json.tool

# Verificar permisos
sudo chmod 644 /etc/wifi_daemon.json
```

### No se conecta a WiFi
```bash
# Verificar NetworkManager
sudo systemctl status NetworkManager

# Probar conexión manual
sudo nmcli dev wifi connect "tu_ssid" password "tu_password"
```

### Interfaz web no funciona
```bash
# Verificar permisos
sudo chown www-data:www-data /var/www/html/wifi_control.php

# Verificar sudoers
sudo visudo -f /etc/sudoers.d/wifi_daemon
```

### Alta temperatura o batería baja
El demonio automáticamente dejará de intentar reconectar si:
- La temperatura supera `max_temp`
- La batería está por debajo de `min_battery`
- Está fuera del horario activo

## Archivos del Sistema

- `/usr/local/bin/wifi_daemon` - Binario principal
- `/etc/wifi_daemon.json` - Archivo de configuración
- `/etc/systemd/system/wifi_daemon.service` - Servicio systemd
- `/var/www/html/wifi_control.php` - Interfaz web
- `/etc/sudoers.d/wifi_daemon` - Permisos sudo para web

## Desinstalación

```bash
sudo systemctl stop wifi_daemon
sudo systemctl disable wifi_daemon
sudo rm /usr/local/bin/wifi_daemon
sudo rm /etc/systemd/system/wifi_daemon.service
sudo rm /etc/wifi_daemon.json
sudo rm /var/www/html/wifi_control.php
sudo rm /etc/sudoers.d/wifi_daemon
sudo systemctl daemon-reload
```
