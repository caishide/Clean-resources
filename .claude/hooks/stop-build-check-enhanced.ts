#!/usr/bin/env node
import { execSync } from 'child_process';
import { readFileSync, existsSync, mkdirSync, writeFileSync, rmSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { homedir } from 'os';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

interface HookInput {
    session_id?: string;
    transcript_path?: string;
}

const CLAUDE_PROJECT_DIR = process.env.CLAUDE_PROJECT_DIR || join(__dirname, '..', '..');
const HOME = process.env.HOME || process.env.USERPROFILE || homedir();

// 统计 TypeScript 错误
function countTscErrors(output: string): number {
    const matches = output.match(/\.tsx?.*:.*error TS[0-9]+:/g);
    return matches ? matches.length : 0;
}

async function main() {
    try {
        // 读取输入
        const input = readFileSync(0, 'utf-8');
        const data: HookInput = JSON.parse(input);

        const sessionId = data.session_id || 'default';
        const cacheDir = join(CLAUDE_PROJECT_DIR, '.claude', 'tsc-cache', sessionId);

        // 检查缓存是否存在
        if (!existsSync(cacheDir)) {
            process.exit(0);
        }

        // 检查是否有被编辑的仓库
        const affectedReposFile = join(cacheDir, 'affected-repos.txt');
        if (!existsSync(affectedReposFile)) {
            process.exit(0);
        }

        // 创建结果目录
        const resultsDir = join(cacheDir, 'results');
        if (!existsSync(resultsDir)) {
            mkdirSync(resultsDir, { recursive: true });
        }

        let totalErrors = 0;
        let hasErrors = false;
        const errorSummaryFile = join(resultsDir, 'error-summary.txt');
        writeFileSync(errorSummaryFile, '');

        // 读取受影响的仓库并运行 TSC 检查
        const affectedRepos = readFileSync(affectedReposFile, 'utf-8')
            .split('\n')
            .map(line => line.trim())
            .filter(Boolean);

        const commandsFile = join(cacheDir, 'commands.txt');
        let commands: Map<string, string> = new Map();

        if (existsSync(commandsFile)) {
            const commandsContent = readFileSync(commandsFile, 'utf-8');
            commandsContent.split('\n').forEach(line => {
                const match = line.match(/^([^:]+):tsc:(.+)$/);
                if (match) {
                    commands.set(match[1], match[2]);
                }
            });
        }

        for (const repo of affectedRepos) {
            const tscCmd = commands.get(repo);
            if (!tscCmd) {
                continue;
            }

            try {
                const repoPath = join(CLAUDE_PROJECT_DIR, repo);
                execSync(tscCmd, {
                    cwd: repoPath,
                    encoding: 'utf-8',
                    stdio: 'pipe'
                });

                // 成功 - 无错误
                writeFileSync(errorSummaryFile, `${repo}:0\n`, { flag: 'a' });
            } catch (error: any) {
                // TSC 失败 - 有错误
                hasErrors = true;
                const output = error.stdout || error.stderr || '';
                const errorCount = countTscErrors(output);
                totalErrors += errorCount;

                // 保存错误输出
                writeFileSync(join(resultsDir, `${repo}-errors.txt`), output);
                writeFileSync(errorSummaryFile, `${repo}:${errorCount}\n`, { flag: 'a' });
            }
        }

        // 如果有错误，准备错误解析
        if (hasErrors) {
            const lastErrorsFile = join(cacheDir, 'last-errors.txt');
            writeFileSync(lastErrorsFile, '');

            // 合并所有错误
            for (const repo of affectedRepos) {
                const errorFile = join(resultsDir, `${repo}-errors.txt`);
                if (existsSync(errorFile)) {
                    const errors = readFileSync(errorFile, 'utf-8');
                    writeFileSync(lastErrorsFile, `=== Errors in ${repo} ===\n${errors}\n\n`, { flag: 'a' });
                }
            }

            // 复制 TSC 命令
            const tscCommandsFile = join(cacheDir, 'tsc-commands.txt');
            if (existsSync(commandsFile)) {
                writeFileSync(tscCommandsFile, readFileSync(commandsFile, 'utf-8'));
            }

            // 格式化消息
            if (totalErrors >= 5) {
                console.error('');
                console.error('## TypeScript Build Errors Detected');
                console.error('');
                console.error(`Found ${totalErrors} TypeScript errors across the following repos:`);

                const summaryLines = readFileSync(errorSummaryFile, 'utf-8').split('\n').filter(Boolean);
                for (const line of summaryLines) {
                    const [repo, count] = line.split(':');
                    if (parseInt(count) > 0) {
                        console.error(`- ${repo}: ${count} errors`);
                    }
                }

                console.error('');
                console.error('Please use the auto-error-resolver agent to fix these errors systematically.');
                console.error('The error details have been cached for the resolver to use.');
                console.error('');
                console.error("Run: Task(subagent_type='auto-error-resolver', description='Fix TypeScript errors', prompt='Fix the TypeScript compilation errors found in the cached error log')");

                process.exit(2);
            } else {
                console.error('');
                console.error('## Minor TypeScript Errors');
                console.error('');
                console.error(`Found ${totalErrors} TypeScript error(s). Here are the details:`);
                console.error('');

                const lastErrors = readFileSync(lastErrorsFile, 'utf-8');
                lastErrors.split('\n').forEach(line => {
                    console.error(`  ${line}`);
                });

                console.error('');
                console.error('Please fix these errors directly in the affected files.');

                process.exit(2);
            }
        } else {
            // 成功 - 清理会话缓存
            rmSync(cacheDir, { recursive: true, force: true });
            process.exit(0);
        }
    } catch (err) {
        console.error('Error in stop-build-check hook:', err);
        process.exit(0); // 不阻塞
    }
}

main().catch(err => {
    console.error('Uncaught error:', err);
    process.exit(0);
});
