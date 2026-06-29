import Foundation

class PHPServerManager {
    let port: Int
    private var process: Process?

    init(port: Int = 8742) {
        self.port = port
    }

    func start(wwwPath: String, routerPath: String) {
        guard let phpPath = findPHP() else {
            NSLog("[MenuBarTasks] PHP not found — install via brew install php")
            return
        }

        stop()

        process = Process()
        process?.executableURL = URL(fileURLWithPath: phpPath)
        // -S host:port -t docroot router.php
        process?.arguments = ["-S", "127.0.0.1:\(port)", "-t", wwwPath, routerPath]
        process?.standardOutput = FileHandle.nullDevice
        process?.standardError  = FileHandle.nullDevice
        process?.currentDirectoryURL = URL(fileURLWithPath: wwwPath)

        do {
            try process?.run()
            NSLog("[MenuBarTasks] PHP server started on 127.0.0.1:\(port)")
        } catch {
            NSLog("[MenuBarTasks] Failed to start PHP: \(error)")
        }
    }

    func stop() {
        process?.terminate()
        process = nil
    }

    private func findPHP() -> String? {
        let candidates = [
            "/opt/homebrew/bin/php",   // arm64 Homebrew
            "/usr/local/bin/php",       // x86 Homebrew
            "/usr/bin/php"              // system (may be stub)
        ]
        return candidates.first { FileManager.default.fileExists(atPath: $0) }
    }
}
