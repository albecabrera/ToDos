APP_NAME  := MenuBarTasks
BUILD_DIR := build
BUNDLE    := $(BUILD_DIR)/$(APP_NAME).app
BIN       := $(BUNDLE)/Contents/MacOS/$(APP_NAME)
RES       := $(BUNDLE)/Contents/Resources

ARCH   := $(shell uname -m)
TARGET := $(ARCH)-apple-macosx13.0

SOURCES := \
	Sources/main.swift \
	Sources/AppDelegate.swift \
	Sources/PHPServerManager.swift \
	Sources/StatusBarController.swift \
	Sources/PopoverController.swift

.PHONY: build run stop clean

# ── Build ──────────────────────────────────────────────────
build: $(BIN)

$(BIN): $(SOURCES) Resources/Info.plist $(shell find www -type f)
	@mkdir -p $(BUNDLE)/Contents/MacOS $(RES)
	@echo "→ Compilando $(APP_NAME) ($(ARCH))…"
	@swiftc $(SOURCES) \
		-framework AppKit \
		-framework WebKit \
		-framework Foundation \
		-target $(TARGET) \
		-O \
		-o $(BIN) \
		2>&1
	@cp Resources/Info.plist $(BUNDLE)/Contents/
	@cp -r www     $(RES)/
	@cp www/router.php $(RES)/router.php
	@echo "✓ Bundle: $(BUNDLE)"

# ── Run ────────────────────────────────────────────────────
run: build stop
	@open $(BUNDLE)
	@echo "✓ $(APP_NAME) corriendo en menubar"

# ── Stop ───────────────────────────────────────────────────
stop:
	@pkill -f "$(APP_NAME)" 2>/dev/null || true
	@pkill -f "127.0.0.1:8742" 2>/dev/null || true

# ── Clean ──────────────────────────────────────────────────
clean: stop
	@rm -rf $(BUILD_DIR)
	@echo "✓ Limpio"
