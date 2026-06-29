import AppKit
import WebKit

class PopoverController: NSViewController, WKNavigationDelegate, WKUIDelegate {
    private var webView: WKWebView!
    private let port: Int
    private var isFirstLoad = true

    /// Called whenever JS reports the pending task count (for menubar badge)
    var onBadgeUpdate: ((Int) -> Void)?

    init(port: Int) {
        self.port = port
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) { fatalError() }

    // MARK: - View

    override func loadView() {
        let effectView = NSVisualEffectView(frame: NSRect(x: 0, y: 0, width: 360, height: 520))
        effectView.material         = .underWindowBackground
        effectView.blendingMode     = .behindWindow
        effectView.state            = .active
        effectView.wantsLayer       = true
        effectView.layer?.cornerRadius      = 12
        effectView.layer?.masksToBounds     = true

        let config = WKWebViewConfiguration()
        let contentController = WKUserContentController()
        contentController.add(WeakScriptHandler(delegate: self), name: "bridge")
        config.userContentController = contentController

        webView = WKWebView(frame: effectView.bounds, configuration: config)
        webView.autoresizingMask   = [.width, .height]
        webView.navigationDelegate = self
        webView.uiDelegate         = self
        webView.setValue(false, forKey: "drawsBackground")
        webView.wantsLayer = true
        webView.layer?.backgroundColor = CGColor.clear
        if #available(macOS 12.0, *) {
            webView.underPageBackgroundColor = .clear
        }

        effectView.addSubview(webView)
        view = effectView
    }

    override func viewDidLoad() {
        super.viewDidLoad()
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.6) { [weak self] in
            self?.loadApp()
        }
    }

    override func viewWillAppear() {
        super.viewWillAppear()
        if !isFirstLoad { loadApp() }
        isFirstLoad = false
    }

    // MARK: - Loading

    private func loadApp() {
        var req = URLRequest(url: URL(string: "http://127.0.0.1:\(port)/")!)
        req.cachePolicy = .reloadIgnoringLocalAndRemoteCacheData
        webView.load(req)
    }

    // MARK: - WKNavigationDelegate

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        let scheme = NSApp.effectiveAppearance.bestMatch(from: [.darkAqua, .aqua])
        let mode   = scheme == .darkAqua ? "dark" : "light"
        webView.evaluateJavaScript(
            "document.documentElement.dataset.scheme = '\(mode)';",
            completionHandler: nil
        )
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            self?.loadApp()
        }
    }
}

// MARK: - WKScriptMessageHandler

extension PopoverController: WKScriptMessageHandler {
    func userContentController(_ ucc: WKUserContentController,
                               didReceive message: WKScriptMessage) {
        guard message.name == "bridge",
              let body = message.body as? [String: Any],
              let type = body["type"] as? String else { return }

        if type == "badge", let count = body["count"] as? Int {
            DispatchQueue.main.async { [weak self] in
                self?.onBadgeUpdate?(count)
            }
        }
    }
}

// Avoids retain cycle with WKUserContentController
private class WeakScriptHandler: NSObject, WKScriptMessageHandler {
    weak var delegate: PopoverController?
    init(delegate: PopoverController) { self.delegate = delegate }
    func userContentController(_ ucc: WKUserContentController,
                               didReceive message: WKScriptMessage) {
        delegate?.userContentController(ucc, didReceive: message)
    }
}
