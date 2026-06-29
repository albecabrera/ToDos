import AppKit
import Carbon.HIToolbox
import ServiceManagement

class StatusBarController {
    let phpManager  = PHPServerManager()
    private var statusItem:     NSStatusItem!
    private var popover:        NSPopover!
    private var popoverVC:      PopoverController!
    private var eventMonitor:   Any?
    private var hotKeyRef:      EventHotKeyRef?
    private var pendingCount    = 0

    init() {
        setupPHP()
        setupStatusItem()
        setupPopover()
        setupGlobalClickMonitor()
        setupHotKey()
    }

    // MARK: - Setup

    private func setupPHP() {
        guard let resources = Bundle.main.resourceURL else { return }
        let wwwPath    = resources.appendingPathComponent("www").path
        let routerPath = resources.appendingPathComponent("router.php").path
        phpManager.start(wwwPath: wwwPath, routerPath: routerPath)
    }

    private func setupStatusItem() {
        // variableLength so badge count text can expand the button
        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)
        guard let button = statusItem.button else { return }

        let cfg = NSImage.SymbolConfiguration(pointSize: 14, weight: .medium)
        button.image = NSImage(systemSymbolName: "checkmark.circle.fill",
                               accessibilityDescription: "Tareas")?
            .withSymbolConfiguration(cfg)
        button.image?.isTemplate = true
        button.imagePosition     = .imageLeft
        button.action            = #selector(handleClick(_:))
        button.target            = self
        button.sendAction(on: [.leftMouseUp, .rightMouseUp])
    }

    private func setupPopover() {
        popoverVC = PopoverController(port: phpManager.port)

        // Badge callback: JS → Swift → menubar icon
        popoverVC.onBadgeUpdate = { [weak self] count in
            self?.updateBadge(count: count)
        }

        popover = NSPopover()
        popover.contentSize  = NSSize(width: 360, height: 520)
        popover.behavior     = .transient
        popover.animates     = true
        popover.contentViewController = popoverVC
    }

    private func setupGlobalClickMonitor() {
        eventMonitor = NSEvent.addGlobalMonitorForEvents(
            matching: [.leftMouseDown, .rightMouseDown]
        ) { [weak self] _ in
            if self?.popover.isShown == true {
                self?.popover.performClose(nil)
            }
        }
    }

    // MARK: - Global HotKey (Cmd+Shift+T — no Accessibility needed)

    private func setupHotKey() {
        var hotKeyID = EventHotKeyID()
        hotKeyID.signature = OSType(0x4D425431)  // 'MBT1'
        hotKeyID.id        = UInt32(1)

        var eventSpec = EventTypeSpec(
            eventClass: OSType(kEventClassKeyboard),
            eventKind:  OSType(kEventHotKeyPressed)
        )

        let selfPtr = Unmanaged.passUnretained(self).toOpaque()

        InstallEventHandler(
            GetApplicationEventTarget(),
            { (_, _, userData) -> OSStatus in
                guard let ptr = userData else { return OSStatus(eventNotHandledErr) }
                let ctrl = Unmanaged<StatusBarController>.fromOpaque(ptr).takeUnretainedValue()
                DispatchQueue.main.async { ctrl.openPopover() }
                return noErr
            },
            1,
            &eventSpec,
            selfPtr,
            nil
        )

        // Cmd+Shift+T  (kVK_ANSI_T = 17)
        RegisterEventHotKey(
            UInt32(kVK_ANSI_T),
            UInt32(cmdKey | shiftKey),
            hotKeyID,
            GetApplicationEventTarget(),
            0,
            &hotKeyRef
        )
    }

    // MARK: - Badge

    func updateBadge(count: Int) {
        pendingCount = count
        guard let button = statusItem.button else { return }

        if count > 0 {
            button.title          = " \(count > 99 ? "99+" : "\(count)")"
            button.font           = .monospacedDigitSystemFont(ofSize: 11, weight: .medium)
        } else {
            button.title = ""
        }
    }

    // MARK: - Actions

    @objc private func handleClick(_ sender: NSStatusBarButton) {
        guard let event = NSApp.currentEvent else { return }
        if event.type == .rightMouseUp {
            showContextMenu(relativeTo: sender)
            return
        }
        if popover.isShown {
            popover.performClose(sender)
        } else {
            openPopover()
        }
    }

    func openPopover() {
        guard let button = statusItem.button, !popover.isShown else { return }
        popover.show(relativeTo: button.bounds, of: button, preferredEdge: .minY)
        NSApp.activate(ignoringOtherApps: true)
    }

    private func showContextMenu(relativeTo button: NSStatusBarButton) {
        let menu = NSMenu()

        let title = NSMenuItem(title: "MenuBar Tasks  v1.1", action: nil, keyEquivalent: "")
        title.isEnabled = false
        menu.addItem(title)

        let hotkey = NSMenuItem(title: "Abrir: ⌘⇧T", action: nil, keyEquivalent: "")
        hotkey.isEnabled = false
        menu.addItem(hotkey)

        menu.addItem(.separator())

        // Login item toggle
        let isLoginEnabled = SMAppService.mainApp.status == .enabled
        let loginItem = NSMenuItem(
            title: isLoginEnabled ? "✓ Iniciar al encender Mac" : "Iniciar al encender Mac",
            action: #selector(toggleLoginItem),
            keyEquivalent: ""
        )
        loginItem.target = self
        menu.addItem(loginItem)

        menu.addItem(.separator())

        let quit = NSMenuItem(title: "Salir", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")
        quit.target = NSApp
        menu.addItem(quit)

        statusItem.menu = menu
        button.performClick(nil)
        statusItem.menu = nil
    }

    @objc private func toggleLoginItem() {
        do {
            if SMAppService.mainApp.status == .enabled {
                try SMAppService.mainApp.unregister()
            } else {
                try SMAppService.mainApp.register()
            }
        } catch {
            NSLog("[MenuBarTasks] Login item toggle failed: \(error)")
        }
    }

    deinit {
        if let m = eventMonitor { NSEvent.removeMonitor(m) }
        if let hk = hotKeyRef   { UnregisterEventHotKey(hk) }
        phpManager.stop()
    }
}
