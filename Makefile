CC = gcc
CFLAGS = -Wall -Wextra -std=c99 -D_GNU_SOURCE
LIBS = -ljson-c
TARGET = wifi_daemon
SOURCE = wifi_daemon.c

.PHONY: all clean install

all: $(TARGET)

$(TARGET): $(SOURCE)
	$(CC) $(CFLAGS) -o $(TARGET) $(SOURCE) $(LIBS)

clean:
	rm -f $(TARGET)

install: $(TARGET)
	sudo cp $(TARGET) /usr/local/bin/
	sudo chmod +x /usr/local/bin/$(TARGET)
