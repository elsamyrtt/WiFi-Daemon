CC=gcc
CFLAGS=-Wall -Wextra -std=c99
LIBS=-ljson-c
TARGET=wifi_daemon
SOURCE=wifi_daemon.c

all: $(TARGET)

$(TARGET): $(SOURCE)
	$(CC) $(CFLAGS) -o $(TARGET) $(SOURCE) $(LIBS)

install: $(TARGET)
	sudo cp $(TARGET) /usr/local/bin/
	sudo chmod +x /usr/local/bin/$(TARGET)
	sudo systemctl daemon-reload
	sudo systemctl restart $(TARGET)

clean:
	rm -f $(TARGET)

service:
	sudo systemctl status $(TARGET)

logs:
	sudo journalctl -u $(TARGET) -f

start:
	sudo systemctl start $(TARGET)

stop:
	sudo systemctl stop $(TARGET)

restart:
	sudo systemctl restart $(TARGET)

enable:
	sudo systemctl enable $(TARGET)

disable:
	sudo systemctl disable $(TARGET)

.PHONY: all install clean service logs start stop restart enable disable
