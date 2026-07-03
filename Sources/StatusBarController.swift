import AppKit
import Carbon.HIToolbox
import ServiceManagement
import UserNotifications
import WebKit

class StatusBarController {
    let phpManager  = PHPServerManager()
    private var statusItem:   NSStatusItem!
    private var popover:      NSPopover!
    private var popoverVC:    PopoverController!
    private var eventMonitor: Any?
    private var hotKeyRef:    EventHotKeyRef?
    private var hotKeyRef2:   EventHotKeyRef?
    private var detailPanel:   NSPanel?
    private var reminderTimer: Timer?

    private var overviewWindow:  NSWindow?
    private let notificationDelegate = NotificationDelegate()

    init() {
        setupPHP()
        setupStatusItem()
        setupPopover()
        setupGlobalClickMonitor()
        setupHotKey()
        setupReminderTimer()
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

        popoverVC.onOpenDetail = { [weak self] taskId, listId in
            self?.openDetailPanel(taskId: taskId, listId: listId)
        }

        popover = NSPopover()
        popover.contentSize  = NSSize(width: 780, height: 840)
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

    // MARK: - HotKeys — ⌥T · ⌘L Vollbild-Übersicht

    private func setupHotKey() {
        var spec = EventTypeSpec(eventClass: OSType(kEventClassKeyboard),
                                 eventKind:  OSType(kEventHotKeyPressed))
        let ptr  = Unmanaged.passUnretained(self).toOpaque()

        InstallEventHandler(GetApplicationEventTarget(),
            { (_, evt, ud) -> OSStatus in
                guard let p = ud, let evt else { return OSStatus(eventNotHandledErr) }
                let c = Unmanaged<StatusBarController>.fromOpaque(p).takeUnretainedValue()
                var hkID = EventHotKeyID()
                GetEventParameter(evt, EventParamName(kEventParamDirectObject),
                                  EventParamType(typeEventHotKeyID), nil,
                                  MemoryLayout<EventHotKeyID>.size, nil, &hkID)
                DispatchQueue.main.async {
                    switch hkID.id {
                    case 1: c.showOverview()
                    case 2: c.showOverview()
                    default: break
                    }
                }
                return noErr
            }, 1, &spec, ptr, nil)

        var id1 = EventHotKeyID(); id1.signature = 0x4D425431; id1.id = 1
        RegisterEventHotKey(UInt32(kVK_ANSI_T), UInt32(optionKey),
                            id1, GetApplicationEventTarget(), 0, &hotKeyRef)

        var id2 = EventHotKeyID(); id2.signature = 0x4D425431; id2.id = 2
        RegisterEventHotKey(UInt32(kVK_ANSI_L), UInt32(cmdKey),
                            id2, GetApplicationEventTarget(), 0, &hotKeyRef2)
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
        guard let btn = statusItem.button else { return }
        if popover.isShown { popover.performClose(nil); return }
        syncAppearance()
        popover.show(relativeTo: btn.bounds, of: btn, preferredEdge: .minY)
        NSApp.activate(ignoringOtherApps: true)
    }

    private func showContextMenu(relativeTo btn: NSStatusBarButton) {
        let menu = NSMenu()

        let title = NSMenuItem(title: "MenuBar Tasks  v1.2", action: nil, keyEquivalent: "")
        title.isEnabled = false
        menu.addItem(title)

        let hk = NSMenuItem(title: "Vollbild-Übersicht: ⌥T", action: nil, keyEquivalent: "")
        hk.isEnabled = false
        menu.addItem(hk)

        menu.addItem(.separator())

        let isLogin = SMAppService.mainApp.status == .enabled
        let loginItem = NSMenuItem(
            title: isLogin ? "✓ Beim Start öffnen" : "Beim Start öffnen",
            action: #selector(toggleLoginItem), keyEquivalent: ""
        )
        loginItem.target = self
        menu.addItem(loginItem)

        menu.addItem(.separator())

        let quit = NSMenuItem(title: "Beenden", action: #selector(NSApplication.terminate(_:)), keyEquivalent: "q")
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

    // MARK: - Reminders

    private func setupReminderTimer() {
        UNUserNotificationCenter.current().delegate = notificationDelegate
        UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .sound, .badge]) { _, _ in }
        reminderTimer = Timer.scheduledTimer(withTimeInterval: 30, repeats: true) { [weak self] _ in
            self?.checkReminders()
        }
        reminderTimer?.fire()
    }

    private func checkReminders() {
        let port = phpManager.port
        guard let url = URL(string: "http://127.0.0.1:\(port)/api/tasks/reminders") else { return }
        URLSession.shared.dataTask(with: url) { [weak self] data, _, _ in
            guard let self,
                  let data,
                  let tasks = try? JSONSerialization.jsonObject(with: data) as? [[String: Any]]
            else { return }
            for task in tasks {
                guard let id    = task["id"]         as? Int,
                      let title = task["title"]      as? String,
                      let min   = task["remind_min"] as? Int
                else { continue }
                DispatchQueue.main.async { self.fireRemindNotification(taskId: id, title: title, minutes: min) }
            }
        }.resume()
    }

    private func fireRemindNotification(taskId: Int, title: String, minutes: Int) {
        let content       = UNMutableNotificationContent()
        content.title     = minutes > 0 ? "⏰ In \(minutes) Min. fällig" : "⏰ Jetzt fällig"
        content.body      = title
        content.sound     = UNNotificationSound(named: UNNotificationSoundName("Glass.aiff"))
        let req = UNNotificationRequest(identifier: "remind-\(taskId)", content: content, trigger: nil)
        UNUserNotificationCenter.current().add(req) { _ in }

        let port = phpManager.port
        guard let url = URL(string: "http://127.0.0.1:\(port)/api/tasks/\(taskId)") else { return }
        var put = URLRequest(url: url)
        put.httpMethod = "PUT"
        put.setValue("application/json", forHTTPHeaderField: "Content-Type")
        put.httpBody = try? JSONSerialization.data(withJSONObject: ["reminded": 1])
        URLSession.shared.dataTask(with: put).resume()
    }

    // MARK: - Fullscreen Overview
    // Öffnet nur noch manuell — über ⌥T oder ⌘L. Keine automatischen Zeitpunkte mehr.

    func showOverview() {
        // Schon offen → nach vorne holen, nicht stapeln
        if let w = overviewWindow {
            w.makeKeyAndOrderFront(nil)
            NSApp.activate(ignoringOtherApps: true)
            return
        }

        guard let screen = NSScreen.main else { return }

        let window = OverlayWindow(
            contentRect: screen.frame,
            styleMask:   [.borderless, .fullSizeContentView],
            backing: .buffered, defer: false
        )
        window.level              = .modalPanel
        window.isOpaque           = false
        window.backgroundColor    = .clear
        window.hasShadow          = false
        window.collectionBehavior = [.canJoinAllSpaces, .fullScreenAuxiliary]
        window.appearance         = NSApp.effectiveAppearance
        window.setFrame(screen.frame, display: true)

        let config = WKWebViewConfiguration()
        let ucc    = WKUserContentController()
        ucc.add(OverviewBridge(target: self), name: "bridge")
        config.userContentController = ucc

        let webView = WKWebView(frame: screen.frame, configuration: config)
        webView.autoresizingMask = [.width, .height]
        webView.appearance       = NSApp.effectiveAppearance
        webView.setValue(false, forKey: "drawsBackground")
        if #available(macOS 12.0, *) { webView.underPageBackgroundColor = .clear }

        var req = URLRequest(url: URL(string: "http://127.0.0.1:\(phpManager.port)/overview")!)
        req.cachePolicy = .reloadIgnoringLocalAndRemoteCacheData
        webView.load(req)

        window.contentView = webView
        window.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)
        overviewWindow = window
        // Esc wird in der Seite behandelt (Bearbeiten umschalten) — ✕ schließt.
    }

    func closeOverview() {
        overviewWindow?.orderOut(nil)
        overviewWindow = nil
    }

    // MARK: - Detail Panel

    func openDetailPanel(taskId: Int?, listId: Int?) {
        detailPanel?.close()

        guard let screen = NSScreen.main?.visibleFrame else { return }
        let w: CGFloat = min(screen.width  * 0.72, 1000)
        let h: CGFloat = min(screen.height * 0.75, 740)
        let x = screen.minX + (screen.width  - w) / 2
        let y = screen.minY + (screen.height - h) / 2

        let panel = NSPanel(
            contentRect: NSRect(x: x, y: y, width: w, height: h),
            styleMask:   [.titled, .closable, .resizable, .fullSizeContentView],
            backing: .buffered, defer: false
        )
        panel.title                      = "Aufgabe — MenuBar Tasks"
        panel.titlebarAppearsTransparent = true
        panel.isMovableByWindowBackground = true
        panel.appearance = NSApp.effectiveAppearance

        let webView = WKWebView(frame: panel.contentView!.bounds)
        webView.autoresizingMask = [.width, .height]
        webView.setValue(false, forKey: "drawsBackground")
        if #available(macOS 12.0, *) { webView.underPageBackgroundColor = NSColor.clear }

        let scheme = NSApp.effectiveAppearance.bestMatch(from: [.darkAqua, .aqua]) == .darkAqua ? "dark" : "light"
        var params = ["scheme=\(scheme)"]
        if let id = taskId { params.append("task_id=\(id)") }
        if let id = listId { params.append("list_id=\(id)") }
        let urlStr = "http://127.0.0.1:\(phpManager.port)/detail?\(params.joined(separator: "&"))"

        webView.load(URLRequest(url: URL(string: urlStr)!))
        panel.contentView = webView
        panel.makeKeyAndOrderFront(nil)
        NSApp.activate(ignoringOtherApps: true)
        detailPanel = panel
    }

    deinit {
        if let m = eventMonitor { NSEvent.removeMonitor(m) }
        if let h = hotKeyRef    { UnregisterEventHotKey(h) }
        if let h = hotKeyRef2   { UnregisterEventHotKey(h) }
        reminderTimer?.invalidate()
        phpManager.stop()
    }
}

// MARK: - Overlay Window (borderless muss key werden können für Esc)

final class OverlayWindow: NSWindow {
    override var canBecomeKey:  Bool { true }
    override var canBecomeMain: Bool { true }
}

// MARK: - Overview Bridge (schwache Referenz → kein Retain-Cycle)

final class OverviewBridge: NSObject, WKScriptMessageHandler {
    weak var target: StatusBarController?
    init(target: StatusBarController) { self.target = target }
    func userContentController(_ ucc: WKUserContentController, didReceive message: WKScriptMessage) {
        guard let body = message.body as? [String: Any],
              body["type"] as? String == "closeOverview" else { return }
        DispatchQueue.main.async { [weak self] in self?.target?.closeOverview() }
    }
}

// MARK: - Notification Delegate (Banner + Sound auch im Vordergrund zeigen)

final class NotificationDelegate: NSObject, UNUserNotificationCenterDelegate {
    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        completionHandler([.banner, .list, .sound])
    }
}
