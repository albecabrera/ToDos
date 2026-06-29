import AppKit
import WebKit

class PopoverController: NSViewController, WKNavigationDelegate, WKUIDelegate {
    private var webView: WKWebView!
    private let port: Int
    private var isFirstLoad = true

    init(port: Int) {
        self.port = port
        super.init(nibName: nil, bundle: nil)
    }

    required init?(coder: NSCoder) { fatalError() }

    // MARK: - View

    override func loadView() {
        // Native macOS blur layer — gives the authentic frosted glass look
        let effectView = NSVisualEffectView(frame: NSRect(x: 0, y: 0, width: 360, height: 500))
        // .underWindowBackground = blur más profundo y oscuro en dark mode
        effectView.material      = .underWindowBackground
        effectView.blendingMode  = .behindWindow
        effectView.state         = .active
        effectView.wantsLayer    = true
        effectView.layer?.cornerRadius = 12
        effectView.layer?.masksToBounds = true

        let config = WKWebViewConfiguration()

        // Allow JS bridge for future use
        let contentController = WKUserContentController()
        contentController.add(WeakScriptHandler(delegate: self), name: "bridge")
        config.userContentController = contentController

        webView = WKWebView(frame: effectView.bounds, configuration: config)
        webView.autoresizingMask    = [.width, .height]
        webView.navigationDelegate  = self
        webView.uiDelegate          = self

        // Transparent so NSVisualEffectView blur shows through
        // (NSView.isOpaque is read-only; this is the correct macOS API)
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
        // Small delay: give PHP server time to bind
        DispatchQueue.main.asyncAfter(deadline: .now() + 0.6) { [weak self] in
            self?.loadApp()
        }
    }

    override func viewWillAppear() {
        super.viewWillAppear()
        // Reload on every open so tasks are always fresh
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
        // Inject system color scheme for CSS media query sync
        let scheme = NSApp.effectiveAppearance.bestMatch(from: [.darkAqua, .aqua])
        let isDark = scheme == .darkAqua ? "true" : "false"
        let js = "document.documentElement.dataset.scheme = '\(isDark == "true" ? "dark" : "light")';"
        webView.evaluateJavaScript(js, completionHandler: nil)
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        // Retry once if PHP wasn't ready
        DispatchQueue.main.asyncAfter(deadline: .now() + 1.0) { [weak self] in
            self?.loadApp()
        }
    }
}

// MARK: - WKScriptMessageHandler

extension PopoverController: WKScriptMessageHandler {
    func userContentController(_ ucc: WKUserContentController,
                               didReceive message: WKScriptMessage) {
        guard message.name == "bridge" else { return }
        // Reserved for future native ↔ JS communication
    }
}

// Avoids retain cycle: WKUserContentController holds a strong ref to handlers
private class WeakScriptHandler: NSObject, WKScriptMessageHandler {
    weak var delegate: PopoverController?
    init(delegate: PopoverController) { self.delegate = delegate }
    func userContentController(_ ucc: WKUserContentController,
                               didReceive message: WKScriptMessage) {
        delegate?.userContentController(ucc, didReceive: message)
    }
}
