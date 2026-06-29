import Foundation

class PHPServerManager {
    let port: Int
    private var process: Process?
    private var lastWwwPath    = ""
    private var lastRouterPath = ""
    private var restartScheduled = false

    init(port: Int = 8742) {
        self.port = port
    }

    func start(wwwPath: String, routerPath: String) {
        lastWwwPath    = wwwPath
        lastRouterPath = routerPath

        guard let phpPath = findPHP() else {
            NSLog("[MenuBarTasks] PHP not found — brew install php")
            return
        }

        stop()

        let p = Process()
        p.executableURL     = URL(fileURLWithPath: phpPath)
        p.arguments         = ["-S", "127.0.0.1:\(port)", "-t", wwwPath, routerPath]
        p.standardOutput    = FileHandle.nullDevice
        p.standardError     = FileHandle.nullDevice
        p.currentDirectoryURL = URL(fileURLWithPath: wwwPath)

        // Watchdog — restart automatically if PHP crashes
        p.terminationHandler = { [weak self] _ in
            guard let self, !self.restartScheduled else { return }
            self.restartScheduled = true
            NSLog("[MenuBarTasks] PHP died — restarting in 2s…")
            DispatchQueue.main.asyncAfter(deadline: .now() + 2) {
                self.restartScheduled = false
                self.start(wwwPath: self.lastWwwPath, routerPath: self.lastRouterPath)
            }
        }

        do {
            try p.run()
            process = p
            NSLog("[MenuBarTasks] PHP server on 127.0.0.1:\(port)")
        } catch {
            NSLog("[MenuBarTasks] PHP start failed: \(error)")
        }
    }

    func stop() {
        restartScheduled = true   // prevent watchdog firing during intentional stop
        process?.terminate()
        process = nil
        restartScheduled = false
    }

    private func findPHP() -> String? {
        ["/opt/homebrew/bin/php", "/usr/local/bin/php", "/usr/bin/php"]
            .first { FileManager.default.fileExists(atPath: $0) }
    }
}
