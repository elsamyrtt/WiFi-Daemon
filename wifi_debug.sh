#!/bin/bash

echo "=== DIAGNÃ“STICO WIFI DAEMON ==="
echo

# 1. Verificar si el servicio estÃ¡ corriendo
echo "1. Estado del servicio:"
systemctl status wifi_daemon --no-pager
echo

# 2. Verificar logs del demonio
echo "2. Ãšltimos logs del demonio:"
journalctl -u wifi_daemon -n 20 --no-pager
echo

# 3. Verificar si el binario existe y es ejecutable
echo "3. Verificar binario:"
ls -la /usr/local/bin/wifi_daemon
echo

# 4. Verificar archivo de configuraciÃ³n
echo "4. ConfiguraciÃ³n actual:"
if [ -f /etc/wifi_daemon.json ]; then
    echo "Archivo existe:"
    ls -la /etc/wifi_daemon.json
    echo "Contenido:"
    cat /etc/wifi_daemon.json
    echo
    echo "ValidaciÃ³n JSON:"
    python3 -m json.tool /etc/wifi_daemon.json > /dev/null 2>&1
    if [ $? -eq 0 ]; then
        echo "âœ“ JSON vÃ¡lido"
    else
        echo "âœ— JSON invÃ¡lido"
    fi
else
    echo "âœ— Archivo /etc/wifi_daemon.json no encontrado"
fi
echo

# 5. Verificar dependencias
echo "5. Dependencias del sistema:"
echo -n "NetworkManager: "
systemctl is-active NetworkManager
echo -n "libjson-c: "
ldconfig -p | grep json-c > /dev/null && echo "instalado" || echo "NO instalado"
echo

# 6. Verificar permisos y archivos del sistema
echo "6. Archivos del sistema:"
echo -n "BaterÃ­a: "
[ -f /sys/class/power_supply/BAT0/capacity ] && echo "encontrado" || echo "NO encontrado"
echo -n "Temperatura: "
[ -f /sys/class/thermal/thermal_zone0/temp ] && echo "encontrado" || echo "NO encontrado"
echo

# 7. Test manual de conectividad
echo "7. Test de conectividad:"
ping -c 1 -W 5 8.8.8.8 > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "âœ“ Internet funciona"
else
    echo "âœ— Sin conexiÃ³n a internet"
fi
echo

# 8. Estado de WiFi
echo "8. Estado WiFi actual:"
nmcli dev wifi
echo
echo "Conexiones activas:"
nmcli con show --active
echo

# 9. Intentar ejecutar el demonio manualmente (para debug)
echo "9. Test manual del demonio:"
echo "Para probar manualmente, ejecuta:"
echo "sudo /usr/local/bin/wifi_daemon"
echo "(Presiona Ctrl+C para salir del test manual)"