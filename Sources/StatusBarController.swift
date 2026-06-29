import AppKit

class StatusBarController {
    let phpManager   = PHPServerManager()
    private var statusItem:   NSStatusItem!
    private var popover:      NSPopover!
    private var popoverVC:    PopoverController!
    private var eventMonitor: Any?

    init() {
        setupPHP()
        setupStatusItem()
        setupPopover()
        setupGlobalClickMonitor()
    }

    // MARK: - Setup

    private func setupPHP() {
        guard let resources = Bundle.main.resourceURL else { return }
        let wwwPath    = resources.appendingPathComponent("www").path
        let routerPath = resources.appendingPathComponent("router.php").path
        phpManager.start(wwwPath: wwwPath, routerPath: routerPath)
    }

    private func setupStatusItem() {
        statusItem = NSStatusBar.system.statusItem(withLength: NSStatusItem.squareLength)
        guard let button = statusItem.button else { return }

        let cfg = NSImage.SymbolConfiguration(pointSize: 15, weight: .medium)
        let img = NSImage(systemSymbolName: "checkmark.circle.fill", accessibilityDescription: "Tareas")
        button.image = img?.withSymbolConfiguration(cfg)
        button.image?.isTemplate = true
        button.action = #selector(handleClick(_:))
        button.target  = self
        button.sendAction(on: [.leftMouseUp, .rightMouseUp])
    }

    private func setupPopover() {
        popoverVC = PopoverController(port: phpManager.port)

        popover = NSPopover()
        popover.contentSize = NSSize(width: 360, height: 500)
        popover.behavior    = .transient
        popover.animates    = true
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
            popover.show(relativeTo: sender.bounds, of: sender, preferredEdge: .minY)
            NSApp.activate(ignoringOtherApps: true)
        }
    }

    private func showContextMenu(relativeTo button: NSStatusBarButton) {
        let menu = NSMenu()
        menu.addItem(NSMenuItem(title: "MenuBar Tasks  v1.0", action: nil, keyEquivalent: ""))
        menu.addItem(.separator())
        let quit = NSMenuItem(title: "Salir", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")
        quit.target = NSApp
        menu.addItem(quit)
        statusItem.menu = menu
        button.performClick(nil)
        statusItem.menu = nil
    }

    deinit {
        if let m = eventMonitor { NSEvent.removeMonitor(m) }
        phpManager.stop()
    }
}
