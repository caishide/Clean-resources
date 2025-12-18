#!/usr/bin/env node
import { execSync } from 'child_process';
import { readFileSync, existsSync, mkdirSync, writeFileSync, readdirSync, statSync, rmSync } from 'fs';
import { join, dirname, basename } from 'path';
import { fileURLToPath } from 'url';
import { homedir } from 'os';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

interface HookInput {
    session_id?: string;
    tool_name?: string;
    tool_input?: any;
}

const CLAUDE_PROJECT_DIR = process.env.CLAUDE_PROJECT_DIR || join(__dirname, '..', '..');
const SESSION_ID = process.env.SESSION_ID || 'default';
const CACHE_ROOT = join(CLAUDE_PROJECT_DIR, '.claude', 'tsc-cache');
const CACHE_DIR = join(CACHE_ROOT, SESSION_ID);

// 创建缓存目录
if (!existsSync(CACHE_DIR)) {
    mkdirSync(CACHE_DIR, { recursive: true });
}

// 获取文件所属的仓库
function getRepoForFile(filePath: string): string | null {
    const relativePath = filePath.replace(CLAUDE_PROJECT_DIR + '/', '').replace(CLAUDE_PROJECT_DIR + '\\', '');
    const match = relativePath.match(/^([^\/\\]+)[\/\\]/);

    if (match) {
        const repo = match[1];
        if (repo === 'src' || repo === 'services') {
            return repo;
        }
    }

    return null;
}

// 检测 TSC 命令
function getTscCommand(repoPath: string): string {
    const repoName = basename(repoPath);

    // 项目特定覆盖
    if (repoName === 'services' && existsSync(join(repoPath, 'traffic-switcher', 'tsconfig.json'))) {
        return 'npx tsc --project traffic-switcher/tsconfig.json --noEmit';
    }

    if (existsSync(join(repoPath, 'tsconfig.app.json'))) {
        return 'npx tsc --project tsconfig.app.json --noEmit';
    } else if (existsSync(join(repoPath, 'tsconfig.build.json'))) {
        return 'npx tsc --project tsconfig.build.json --noEmit';
    } else if (existsSync(join(repoPath, 'tsconfig.json'))) {
        const content = readFileSync(join(repoPath, 'tsconfig.json'), 'utf-8');
        if (content.includes('"references"')) {
            if (existsSync(join(repoPath, 'tsconfig.app.json'))) {
                return 'npx tsc --project tsconfig.app.json --noEmit';
            } else if (existsSync(join(repoPath, 'tsconfig.src.json'))) {
                return 'npx tsc --project tsconfig.src.json --noEmit';
            } else {
                return 'npx tsc --build --noEmit';
            }
        } else {
            return 'npx tsc --noEmit';
        }
    }

    return 'npx tsc --noEmit';
}

// 运行 TSC 检查
function runTscCheck(repo: string): { success: boolean; output: string } {
    const repoPath = join(CLAUDE_PROJECT_DIR, repo);
    const cacheFile = join(CACHE_DIR, `${repo}-tsc-cmd.cache`);

    if (!existsSync(repoPath)) {
        return { success: false, output: `Repository path not found: ${repoPath}` };
    }

    // 获取或缓存 TSC 命令
    let tscCmd: string;
    if (existsSync(cacheFile) && !process.env.FORCE_DETECT) {
        tscCmd = readFileSync(cacheFile, 'utf-8').trim();
    } else {
        tscCmd = getTscCommand(repoPath);
        writeFileSync(cacheFile, tscCmd);
    }

    try {
        const output = execSync(tscCmd, {
            cwd: repoPath,
            encoding: 'utf-8',
            stdio: 'pipe'
        });
        return { success: true, output };
    } catch (error: any) {
        return { success: false, output: error.stdout || error.stderr || error.message };
    }
}

// 清理旧缓存（按项目目录下的 .claude/tsc-cache）
function cleanupOldCache() {
    const baseCacheDir = CACHE_ROOT;
    if (!existsSync(baseCacheDir)) return;

    const now = Date.now();
    const sevenDays = 7 * 24 * 60 * 60 * 1000;

    try {
        const dirs = readdirSync(baseCacheDir);
        for (const dir of dirs) {
            const fullPath = join(baseCacheDir, dir);
            try {
                const stats = statSync(fullPath);
                if (stats.isDirectory() && (now - stats.mtimeMs) > sevenDays) {
                    rmSync(fullPath, { recursive: true, force: true });
                }
            } catch (e) {
                // 忽略错误
            }
        }
    } catch (e) {
        // 忽略错误
    }
}

async function main() {
    try {
        // 读取输入
        const input = readFileSync(0, 'utf-8');
        const data: HookInput = JSON.parse(input);

        const toolName = data.tool_name || '';
        const toolInput = data.tool_input || {};

        // 只处理文件修改工具
        if (!['Write', 'Edit', 'MultiEdit'].includes(toolName)) {
            process.exit(0);
        }

        // 提取文件路径
        let filePaths: string[] = [];
        if (toolName === 'MultiEdit' && toolInput.edits) {
            filePaths = toolInput.edits.map((e: any) => e.file_path).filter(Boolean);
        } else if (toolInput.file_path) {
            filePaths = [toolInput.file_path];
        }

        // 只检查 TS/JS 文件
        const tsFiles = filePaths.filter(f => /\.(ts|tsx|js|jsx)$/.test(f));
        if (tsFiles.length === 0) {
            process.exit(0);
        }

        // 收集需要检查的仓库
        const repos = new Set<string>();
        for (const file of tsFiles) {
            const repo = getRepoForFile(file);
            if (repo) {
                repos.add(repo);
            }
        }

        if (repos.size === 0) {
            process.exit(0);
        }

        const reposArray = Array.from(repos);
        console.error(`⚡ TypeScript check on: ${reposArray.join(' ')}`);

        let errorCount = 0;
        let errorOutput = '';
        const failedRepos: string[] = [];

        for (const repo of reposArray) {
            process.stderr.write(`  Checking ${repo}... `);

            const result = runTscCheck(repo);

            if (!result.success || result.output.includes('error TS')) {
                console.error('❌ Errors found');
                errorCount++;
                failedRepos.push(repo);
                errorOutput += `\n\n=== Errors in ${repo} ===\n${result.output}`;
            } else {
                console.error('✅ OK');
            }
        }

        if (errorCount > 0) {
            // 保存错误信息
            writeFileSync(join(CACHE_DIR, 'last-errors.txt'), errorOutput);
            writeFileSync(join(CACHE_DIR, 'affected-repos.txt'), failedRepos.join('\n'));

            // 保存 TSC 命令
            let commandsContent = '# TSC Commands by Repo\n';
            for (const repo of failedRepos) {
                const cacheFile = join(CACHE_DIR, `${repo}-tsc-cmd.cache`);
                const cmd = existsSync(cacheFile) ? readFileSync(cacheFile, 'utf-8').trim() : 'npx tsc --noEmit';
                commandsContent += `${repo}: ${cmd}\n`;
            }
            writeFileSync(join(CACHE_DIR, 'tsc-commands.txt'), commandsContent);

            // 输出错误信息
            console.error('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            console.error(`🚨 TypeScript errors found in ${errorCount} repo(s): ${failedRepos.join(' ')}`);
            console.error('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
            console.error('');
            console.error('👉 IMPORTANT: Use the auto-error-resolver agent to fix the errors');
            console.error('');
            console.error('WE DO NOT LEAVE A MESS BEHIND');
            console.error('Error Preview:');

            const errorLines = errorOutput.split('\n').filter(l => l.includes('error TS'));
            errorLines.slice(0, 10).forEach(line => console.error(line));

            if (errorLines.length > 10) {
                console.error(`... and ${errorLines.length - 10} more errors`);
            }
            console.error('');

            process.exit(1);
        }

        // 清理旧缓存
        cleanupOldCache();

        process.exit(0);
    } catch (err) {
        console.error('Error in tsc-check hook:', err);
        process.exit(0); // 不阻塞
    }
}

main().catch(err => {
    console.error('Uncaught error:', err);
    process.exit(0);
});
