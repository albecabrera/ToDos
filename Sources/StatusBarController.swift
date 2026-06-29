import AppKit
import Carbon.HIToolbox
import ServiceManagement

class StatusBarController {
    let phpManager  = PHPServerManager()
    private var statusItem:   NSStatusItem!
    private var popover:      NSPopover!
    private var popoverVC:    PopoverController!
    private var eventMonitor: Any?
    private var hotKeyRef:    EventHotKeyRef?

    init() {
        setupPHP()
        setupStatusItem()
        setupPopover()
        setupGlobalClickMonitor()
        setupHotKey()
    }

    // MARK: - Setup

    private func setupPHP() {
        guard let res = Bundle.main.resourceURL else { return }
        phpManager.start(
            wwwPath:    res.appendingPathComponent("www").path,
            routerPath: res.appendingPathComponent("router.php").path
        )
    }

    private func setupStatusItem() {
        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.variableLength)
        guard let btn = statusItem.button else { return }
        let cfg = NSImage.SymbolConfiguration(pointSize: 14, weight: .medium)
        btn.image = NSImage(systemSymbolName: "checkmark.circle.fill",
                            accessibilityDescription: "Tareas")?.withSymbolConfiguration(cfg)
        btn.image?.isTemplate = true
        btn.imagePosition     = .imageLeft
        btn.action            = #selector(handleClick(_:))
        btn.target            = self
        btn.sendAction(on: [.leftMouseUp, .rightMouseUp])
    }

    private func setupPopover() {
        popoverVC = PopoverController(port: phpManager.port)
        popoverVC.onBadgeUpdate = { [weak self] count, hasOverdue in
            self?.updateBadge(count: count, hasOverdue: hasOverdue)
        }

        popover = NSPopover()
        popover.contentSize  = NSSize(width: 360, height: 540)
        popover.behavior     = .transient
        popover.animates     = true
        popover.contentViewController = popoverVC
        syncAppearance()

        DistributedNotificationCenter.default().addObserver(
            forName: .init("AppleInterfaceThemeChangedNotification"),
            object: nil, queue: .main
        ) { [weak self] _ in self?.syncAppearance() }
    }

    private func syncAppearance() {
        popover.appearance = NSApp.effectiveAppearance
    }

    private func setupGlobalClickMonitor() {
        eventMonitor = NSEvent.addGlobalMonitorForEvents(
            matching: [.leftMouseDown, .rightMouseDown]
        ) { [weak self] _ in
            if self?.popover.isShown == true { self?.popover.performClose(nil) }
        }
    }

    // MARK: - HotKey Cmd+Shift+T

    private func setupHotKey() {
        var id   = EventHotKeyID(); id.signature = 0x4D425431; id.id = 1
        var spec = EventTypeSpec(eventClass: OSType(kEventClassKeyboard),
                                 eventKind:  OSType(kEventHotKeyPressed))
        let ptr  = Unmanaged.passUnretained(self).toOpaque()

        InstallEventHandler(GetApplicationEventTarget(),
            { (_, _, ud) -> OSStatus in
                guard let p = ud else { return OSStatus(eventNotHandledErr) }
                let c = Unmanaged<StatusBarController>.fromOpaque(p).takeUnretainedValue()
                DispatchQueue.main.async { c.openPopover() }
                return noErr
            }, 1, &spec, ptr, nil)

        RegisterEventHotKey(UInt32(kVK_ANSI_T), UInt32(cmdKey | shiftKey),
                            id, GetApplicationEventTarget(), 0, &hotKeyRef)
    }

    // MARK: - Badge

    func updateBadge(count: Int, hasOverdue: Bool) {
        guard let btn = statusItem.button else { return }
        if count > 0 {
            let label  = count > 99 ? "99+" : "\(count)"
            let color: NSColor = hasOverdue ? .systemRed : .labelColor
            let attrs: [NSAttributedString.Key: Any] = [
                .foregroundColor: color,
                .font: NSFont.monospacedDigitSystemFont(ofSize: 11, weight: .medium)
            ]
            btn.attributedTitle = NSAttributedString(string: " \(label)", attributes: attrs)
        } else {
            btn.attributedTitle = NSAttributedString(string: "")
        }
    }

    // MARK: - Actions

    @objc private func handleClick(_ sender: NSStatusBarButton) {
        guard let ev = NSApp.currentEvent else { return }
        if ev.type == .rightMouseUp { showContextMenu(relativeTo: sender); return }
        if popover.isShown { popover.performClose(sender) } else { openPopover() }
    }

    func openPopover() {
        guard let btn = statusItem.button, !popover.isShown else { return }
        syncAppearance()
        popover.show(relativeTo: btn.bounds, of: btn, preferredEdge: .minY)
        NSApp.activate(ignoringOtherApps: true)
    }

    private func showContextMenu(relativeTo btn: NSStatusBarButton) {
        let menu = NSMenu()

        let title = NSMenuItem(title: "MenuBar Tasks  v1.2", action: nil, keyEquivalent: "")
        title.isEnabled = false
        menu.addItem(title)

        let hk = NSMenuItem(title: "Abrir: ⌘⇧T", action: nil, keyEquivalent: "")
        hk.isEnabled = false
        menu.addItem(hk)

        menu.addItem(.separator())

        let isLogin = SMAppService.mainApp.status == .enabled
        let loginItem = NSMenuItem(
            title: isLogin ? "✓ Iniciar al encender Mac" : "Iniciar al encender Mac",
            action: #selector(toggleLoginItem), keyEquivalent: ""
        )
        loginItem.target = self
        menu.addItem(loginItem)

        menu.addItem(.separator())

        let quit = NSMenuItem(title: "Salir", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")
        quit.target = NSApp
        menu.addItem(quit)

        statusItem.menu = menu
        btn.performClick(nil)
        statusItem.menu = nil
    }

    @objc private func toggleLoginItem() {
        do {
            if SMAppService.mainApp.status == .enabled { try SMAppService.mainApp.unregister() }
            else { try SMAppService.mainApp.register() }
        } catch { NSLog("[MenuBarTasks] Login item: \(error)") }
    }

    deinit {
        if let m = eventMonitor { NSEvent.removeMonitor(m) }
        if let h = hotKeyRef    { UnregisterEventHotKey(h) }
        phpManager.stop()
    }
}
