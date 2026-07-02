import AppKit
import WebKit

class PopoverController: NSViewController, WKNavigationDelegate, WKUIDelegate {
    private var webView: WKWebView!
    private let port: Int
    private var isFirstLoad = true

    var onBadgeUpdate:  ((Int, Bool) -> Void)?
    var onOpenDetail:   ((Int?, Int?) -> Void)?   // (taskId, listId)

    init(port: Int) {
        self.port = port
        super.init(nibName: nil, bundle: nil)
    }
    required init?(coder: NSCoder) { fatalError() }

    // MARK: - View

    override func loadView() {
        let effectView = NSVisualEffectView(frame: NSRect(x: 0, y: 0, width: 360, height: 540))
        effectView.material         = .underWindowBackground
        effectView.blendingMode     = .behindWindow
        effectView.state            = .active
        effectView.wantsLayer       = true
        effectView.layer?.cornerRadius   = 12
        effectView.layer?.masksToBounds  = true

        let config = WKWebViewConfiguration()
        let ucc    = WKUserContentController()
        ucc.add(WeakScriptHandler(delegate: self), name: "bridge")
        config.userContentController = ucc

        webView = WKWebView(frame: effectView.bounds, configuration: config)
        webView.autoresizingMask   = [.width, .height]
        webView.navigationDelegate = self
        webView.uiDelegate         = self
        webView.setValue(false, forKey: "drawsBackground")
        webView.wantsLayer = true
        webView.layer?.backgroundColor = CGColor.clear
        if #available(macOS 12.0, *) { webView.underPageBackgroundColor = .clear }

        effectView.addSubview(webView)
        view = effectView
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.6) { [weak self] in self?.loadApp() }
    }

    override func viewWillAppear() {
        super.viewWillAppear()
        if !isFirstLoad { loadApp() }
        isFirstLoad = false
    }

    // MARK: - Load

    private func loadApp() {
        var req = URLRequest(url: URL(string: "http://127.0.0.1:\(port)/")!)
        req.cachePolicy = .reloadIgnoringLocalAndRemoteCacheData
        webView.load(req)
    }

    // MARK: - WKNavigationDelegate

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        let mode = NSApp.effectiveAppearance.bestMatch(from: [.darkAqua, .aqua]) == .darkAqua ? "dark" : "light"
        webView.evaluateJavaScript("document.documentElement.dataset.scheme='\(mode)';", completionHandler: nil)
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        DispatchQueue.main.asyncAfter(deadline: .now() + 1) { [weak self] in self?.loadApp() }
    }
}

// MARK: - WKScriptMessageHandler

extension PopoverController: WKScriptMessageHandler {
    func userContentController(_ ucc: WKUserContentController, didReceive message: WKScriptMessage) {
        guard message.name == "bridge",
              let body = message.body as? [String: Any],
              let type = body["type"] as? String else { return }

        DispatchQueue.main.async { [weak self] in
            switch type {

            case "badge":
                let count     = body["count"]      as? Int  ?? 0
                let hasOverdue = body["hasOverdue"] as? Bool ?? false
                self?.onBadgeUpdate?(count, hasOverdue)

            case "haptic":
                NSHapticFeedbackManager.defaultPerformer.perform(
                    .alignment, performanceTime: .default
                )

            case "sound":
                let name = body["name"] as? String ?? "Pop"
                NSSound(named: NSSound.Name(name))?.play()

            case "copy":
                if let text = body["text"] as? String {
                    NSPasteboard.general.clearContents()
                    NSPasteboard.general.setString(text, forType: .string)
                }

            case "openDetail":
                let taskId = body["taskId"] as? Int
                let listId = body["listId"] as? Int
                self?.onOpenDetail?(taskId, listId)

            default: break
            }
        }
    }
}

private class WeakScriptHandler: NSObject, WKScriptMessageHandler {
    weak var delegate: PopoverController?
    init(delegate: PopoverController) { self.delegate = delegate }
    func userContentController(_ ucc: WKUserContentController, didReceive message: WKScriptMessage) {
        delegate?.userContentController(ucc, didReceive: message)
    }
}
