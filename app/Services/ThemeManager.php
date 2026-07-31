<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Facades\Cache;

class ThemeManager
{
    protected ?string $activeTheme = null;

    // =========================================================================
    // Tema ativo
    // =========================================================================

    public function getActive(): string
    {
        if ($this->activeTheme) {
            return $this->activeTheme;
        }

        $this->activeTheme = Cache::remember('active_theme', 3600, function () {
            return Store::first()?->active_theme ?? 'default';
        });

        return $this->activeTheme;
    }

    public function setActive(string $themeName): void
    {
        $store = Store::first();
        if ($store) {
            $store->update(['active_theme' => $themeName]);
            Cache::forget('active_theme');
            $this->activeTheme = $themeName;
        }
    }

    // =========================================================================
    // Listar temas
    // =========================================================================

    public function list(): array
    {
        $themes   = [];
        $active   = $this->getActive();
        $basePath = public_path('themes');

        if (!is_dir($basePath)) {
            return $themes;
        }

        foreach (glob($basePath . '/*', GLOB_ONLYDIR) as $dir) {
            $name     = basename($dir);
            $meta     = $this->readSchemaMeta($name);

            $themes[] = [
                'name'      => $name,
                'label'     => $meta['theme_name'] ?? $name,
                'version'   => $meta['theme_version'] ?? null,
                'author'    => $meta['theme_author'] ?? null,
                'is_active' => $name === $active,
                'preview'   => file_exists($dir . '/assets/images/preview.png')
                    ? asset("themes/{$name}/assets/images/preview.png")
                    : (file_exists($dir . '/assets/preview.png')
                        ? asset("themes/{$name}/assets/preview.png")
                        : null),
            ];
        }

        return $themes;
    }

    // =========================================================================
    // Settings (settings_data.json)
    // =========================================================================

    public function getSettings(?string $theme = null): array
    {
        $theme    = $theme ?? $this->getActive();
        $dataPath = public_path("themes/{$theme}/config/settings_data.json");

        if (!file_exists($dataPath)) {
            return [];
        }

        return json_decode(file_get_contents($dataPath), true) ?? [];
    }

    public function saveSettings(array $data, ?string $theme = null): void
    {
        $theme    = $theme ?? $this->getActive();
        $dataPath = public_path("themes/{$theme}/config/settings_data.json");

        $merged = array_merge($this->getSettings($theme), $data);

        file_put_contents(
            $dataPath,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        Cache::forget("theme_settings_{$theme}");
    }

    // =========================================================================
    // Schema (settings_schema.json)
    // =========================================================================

    public function getSchema(?string $theme = null): array
    {
        $theme      = $theme ?? $this->getActive();
        $schemaPath = public_path("themes/{$theme}/config/settings_schema.json");

        if (!file_exists($schemaPath)) {
            return [];
        }

        $data = json_decode(file_get_contents($schemaPath), true);

        return is_array($data) ? $data : [];
    }

    /**
     * Lê metadados do tema a partir do settings_schema.json.
     * Resiliente a schemas sem chave 'meta' e a JSONs malformados.
     */
    private function readSchemaMeta(string $theme): array
    {
        $schemaPath = public_path("themes/{$theme}/config/settings_schema.json");

        if (!file_exists($schemaPath)) {
            return [];
        }

        $data = json_decode(file_get_contents($schemaPath), true);

        if (!is_array($data)) {
            return [];
        }

        // Suporta metadados em 'meta' ou diretamente na raiz do JSON
        return $data['meta'] ?? array_filter($data, fn ($v) => !is_array($v));
    }

    // =========================================================================
    // Upload de tema ZIP
    // =========================================================================

    public function installFromZip(string $zipPath): array
    {
        $zip = new \ZipArchive();

        if ($zip->open($zipPath) !== true) {
            return ['success' => false, 'message' => 'Não foi possível abrir o arquivo ZIP.'];
        }

        // Detectar nome do tema pela pasta raiz do ZIP
        $themeName = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $parts = explode('/', $zip->getNameIndex($i));
            if (count($parts) >= 2 && !empty($parts[0])) {
                $themeName = $parts[0];
                break;
            }
        }

        if (!$themeName) {
            $zip->close();
            return ['success' => false, 'message' => 'Estrutura de ZIP inválida. A pasta raiz deve ser o nome do tema.'];
        }

        $destPath = public_path("themes/{$themeName}");

        // Backup do tema existente antes de sobrescrever
        if (is_dir($destPath)) {
            $backupPath = $destPath . '_backup_' . time();
            if (!$this->moveDirectory($destPath, $backupPath)) {
                $zip->close();
                return ['success' => false, 'message' => 'Não foi possível criar backup do tema existente.'];
            }
        }

        $zip->extractTo(public_path('themes'));
        $zip->close();

        // Validar estrutura mínima exigida
        $required = [
            'layout/theme.twig',
            'templates/index.twig',
            'config/settings_schema.json',
        ];

        foreach ($required as $file) {
            if (!file_exists("{$destPath}/{$file}")) {
                $this->deleteDirectory($destPath);
                return ['success' => false, 'message' => "Arquivo obrigatório não encontrado: {$file}"];
            }
        }

        // Segurança: não permitir arquivos PHP no tema
        $phpFiles = $this->findPhpFiles($destPath);
        if (!empty($phpFiles)) {
            $this->deleteDirectory($destPath);
            return ['success' => false, 'message' => 'O tema contém arquivos PHP, o que não é permitido.'];
        }

        return ['success' => true, 'theme' => $themeName, 'message' => "Tema '{$themeName}' instalado com sucesso!"];
    }

    // =========================================================================
    // Remover tema
    // =========================================================================

    public function delete(string $themeName): array
    {
        if ($themeName === 'default') {
            return ['success' => false, 'message' => 'O tema padrão não pode ser removido.'];
        }

        if ($themeName === $this->getActive()) {
            return ['success' => false, 'message' => 'Não é possível remover o tema ativo.'];
        }

        $path = public_path("themes/{$themeName}");
        if (!is_dir($path)) {
            return ['success' => false, 'message' => 'Tema não encontrado.'];
        }

        $this->deleteDirectory($path);

        return ['success' => true, 'message' => "Tema '{$themeName}' removido."];
    }

    // =========================================================================
    // Helpers privados
    // =========================================================================

    /**
     * Move um diretório com tratamento de erro.
     * Usa rename() (eficiente quando no mesmo filesystem) com fallback informado.
     */
    private function moveDirectory(string $from, string $to): bool
    {
        if (!is_dir($from)) return false;

        // rename() falha entre filesystems distintos; capturamos o erro
        set_error_handler(fn () => null);
        $result = rename($from, $to);
        restore_error_handler();

        return $result;
    }

    private function findPhpFiles(string $dir): array
    {
        $found    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $found[] = $file->getPathname();
            }
        }
        return $found;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}