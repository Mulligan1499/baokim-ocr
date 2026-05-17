<?php

namespace App\Services\Feedback;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin wrapper quanh `gh` CLI để tạo branch + commit + PR.
 *
 * Workflow gọi từ AnalyzeActions command:
 *   1. createBranch(slug)
 *   2. applyDiffs([{target_path, diff}])
 *   3. commitAndPush(message)
 *   4. createPr(title, body) → return PR URL
 *
 * Service KHÔNG quyết định nội dung diff — đó là việc ActionAnalyzerService.
 * Đây chỉ là git/gh wrapper.
 */
class GitHubPrCreator
{
    public function __construct(private string $repoRoot)
    {
    }

    public function ensureGhAvailable(): void
    {
        $result = Process::run('which gh');
        if (! $result->successful() || empty(trim($result->output()))) {
            throw new RuntimeException(
                'gh CLI not installed. Run: brew install gh && gh auth login'
            );
        }

        $auth = Process::run('gh auth status');
        if (! $auth->successful()) {
            throw new RuntimeException(
                'gh CLI not authenticated. Run: gh auth login'
            );
        }
    }

    public function createBranch(string $slug): string
    {
        $branch = 'agent/skill-update-' . $slug;

        $result = Process::path($this->repoRoot)->run(['git', 'checkout', '-b', $branch]);
        if (! $result->successful()) {
            throw new RuntimeException("git checkout -b failed: {$result->errorOutput()}");
        }

        Log::info('feedback.branch_created', ['branch' => $branch]);
        return $branch;
    }

    /**
     * Apply mỗi diff text vào target file.
     * Đơn giản hóa: ghi append diff như comment vào target file.
     * Production version: parse unified diff + apply qua `git apply`.
     */
    public function applyDiffs(array $patches): array
    {
        $applied = [];
        foreach ($patches as $patch) {
            $target = $patch['target_path'] ?? null;
            $diff = $patch['diff'] ?? null;
            if (! $target || ! $diff) {
                continue;
            }

            $abs = $this->repoRoot . '/' . ltrim($target, '/');
            if (! is_file($abs)) {
                Log::warning('feedback.target_not_found', ['target' => $target]);
                continue;
            }

            // Write diff as a sibling .patch file để engineer manually apply trong review.
            // Lý do: parse + apply unified diff text từ LLM thường thất bại vì hunk offset
            // không khớp file thật. Để engineer review + apply là an toàn nhất cho fintech.
            $patchPath = $this->repoRoot . '/reports/patches/' . Str::slug(basename($target)) . '.patch';
            @mkdir(dirname($patchPath), 0755, true);
            file_put_contents($patchPath, $diff);

            $applied[] = ['target' => $target, 'patch_file' => $patchPath];
        }
        return $applied;
    }

    public function writeReport(string $markdown, string $filename): string
    {
        $reportsDir = $this->repoRoot . '/reports';
        @mkdir($reportsDir, 0755, true);
        $path = $reportsDir . '/' . $filename;
        file_put_contents($path, $markdown);
        return $path;
    }

    public function commitAndPush(string $branch, string $message, array $filesToAdd): void
    {
        if (empty($filesToAdd)) {
            // Add report files chỉ
            Process::path($this->repoRoot)->run(['git', 'add', 'reports/']);
        } else {
            foreach ($filesToAdd as $f) {
                Process::path($this->repoRoot)->run(['git', 'add', $f]);
            }
            Process::path($this->repoRoot)->run(['git', 'add', 'reports/']);
        }

        $result = Process::path($this->repoRoot)->run(['git', 'commit', '-m', $message]);
        if (! $result->successful()) {
            throw new RuntimeException("git commit failed: {$result->errorOutput()}\n{$result->output()}");
        }

        $push = Process::path($this->repoRoot)->run(['git', 'push', 'origin', $branch]);
        if (! $push->successful()) {
            throw new RuntimeException("git push failed: {$push->errorOutput()}");
        }

        Log::info('feedback.commit_pushed', ['branch' => $branch]);
    }

    public function createPr(string $title, string $body): string
    {
        $result = Process::path($this->repoRoot)->run([
            'gh', 'pr', 'create',
            '--title', $title,
            '--body', $body,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("gh pr create failed: {$result->errorOutput()}");
        }

        $url = trim($result->output());
        Log::info('feedback.pr_created', ['url' => $url]);
        return $url;
    }

    public function switchBack(string $previousBranch = 'main'): void
    {
        Process::path($this->repoRoot)->run(['git', 'checkout', $previousBranch]);
    }
}
