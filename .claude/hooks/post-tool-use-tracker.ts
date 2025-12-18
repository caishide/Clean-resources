#!/usr/bin/env node
import { readFileSync, existsSync, mkdirSync, writeFileSync, appendFileSync } from 'fs';
import { join, dirname, sep } from 'path';
import { fileURLToPath } from 'url';
import { homedir } from 'os';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

interface ToolInfo {
    tool_name?: string;
    tool_input?: {
        file_path?: string;
    };
    session_id?: string;
}

const CLAUDE_PROJECT_DIR = process.env.CLAUDE_PROJECT_DIR || join(__dirname, '..', '..');

// 从文件路径检测仓库
function detectRepo(filePath: string): string {
    const relativePath = filePath
        .replace(CLAUDE_PROJECT_DIR + '/', '')
        .replace(CLAUDE_PROJECT_DIR + '\\', '')
        .replace(/\\/g, '/');

    const parts = relativePath.split('/');
    const repo = parts[0];

    // 常见项目目录模式
    const frontendPatterns = ['frontend', 'client', 'web', 'app', 'ui'];
    const backendPatterns = ['backend', 'server', 'api', 'src', 'services'];
    const databasePatterns = ['database', 'prisma', 'migrations'];

    if (frontendPatterns.includes(repo) || backendPatterns.includes(repo) || databasePatterns.includes(repo)) {
        return repo;
    }

    // Monorepo 结构
    if (repo === 'packages' || repo === 'examples') {
        const subPkg = parts[1];
        return subPkg ? `${repo}/${subPkg}` : repo;
    }

    // 根目录源文件
    if (!relativePath.includes('/')) {
        return 'root';
    }

    return 'unknown';
}

// 获取构建命令
function getBuildCommand(repo: string): string {
    const repoPath = join(CLAUDE_PROJECT_DIR, repo);
    const packageJsonPath = join(repoPath, 'package.json');

    if (existsSync(packageJsonPath)) {
        const content = readFileSync(packageJsonPath, 'utf-8');
        if (content.includes('"build"')) {
            // 检测包管理器
            if (existsSync(join(repoPath, 'pnpm-lock.yaml'))) {
                return `cd ${repoPath} && pnpm build`;
            } else if (existsSync(join(repoPath, 'package-lock.json'))) {
                return `cd ${repoPath} && npm run build`;
            } else if (existsSync(join(repoPath, 'yarn.lock'))) {
                return `cd ${repoPath} && yarn build`;
            } else {
                return `cd ${repoPath} && npm run build`;
            }
        }
    }

    // Prisma 特殊处理
    if (repo === 'database' || repo.includes('prisma')) {
        const schemaPath1 = join(repoPath, 'schema.prisma');
        const schemaPath2 = join(repoPath, 'prisma', 'schema.prisma');
        if (existsSync(schemaPath1) || existsSync(schemaPath2)) {
            return `cd ${repoPath} && npx prisma generate`;
        }
    }

    return '';
}

// 获取 TSC 命令
function getTscCommand(repo: string): string {
    const repoPath = join(CLAUDE_PROJECT_DIR, repo);

    // 项目特定覆盖
    if (repo === 'src') {
        return `cd "${CLAUDE_PROJECT_DIR}" && npx tsc --noEmit`;
    }

    if (repo === 'services') {
        const trafficSwitcherConfig = join(repoPath, 'traffic-switcher', 'tsconfig.json');
        if (existsSync(trafficSwitcherConfig)) {
            return `cd "${join(repoPath, 'traffic-switcher')}" && npx tsc --project tsconfig.json --noEmit`;
        }
    }

    // 通用回退
    if (existsSync(join(repoPath, 'tsconfig.json'))) {
        if (existsSync(join(repoPath, 'tsconfig.app.json'))) {
            return `cd ${repoPath} && npx tsc --project tsconfig.app.json --noEmit`;
        } else {
            return `cd ${repoPath} && npx tsc --noEmit`;
        }
    }

    return '';
}

async function main() {
    try {
        // 读取输入
        const input = readFileSync(0, 'utf-8');
        const toolInfo: ToolInfo = JSON.parse(input);

        const toolName = toolInfo.tool_name || '';
        const filePath = toolInfo.tool_input?.file_path || '';
        const sessionId = toolInfo.session_id || 'default';

        // 跳过非编辑工具
        if (!['Edit', 'MultiEdit', 'Write'].includes(toolName) || !filePath) {
            process.exit(0);
        }

        // 跳过 markdown 文件
        if (/\.(md|markdown)$/.test(filePath)) {
            process.exit(0);
        }

        // 创建缓存目录
        const cacheDir = join(CLAUDE_PROJECT_DIR, '.claude', 'tsc-cache', sessionId);
        if (!existsSync(cacheDir)) {
            mkdirSync(cacheDir, { recursive: true });
        }

        // 检测仓库
        const repo = detectRepo(filePath);

        // 跳过未知仓库
        if (repo === 'unknown' || !repo) {
            process.exit(0);
        }

        // 记录编辑的文件（使用 Tab 分隔，避免 Windows 路径中的冒号冲突）
        const timestamp = Math.floor(Date.now() / 1000);
        appendFileSync(
            join(cacheDir, 'edited-files.log'),
            `${timestamp}\t${toolName}\t${filePath}\n`,
        );

        // 更新受影响的仓库列表
        const affectedReposFile = join(cacheDir, 'affected-repos.txt');
        let affectedRepos: string[] = [];
        if (existsSync(affectedReposFile)) {
            affectedRepos = readFileSync(affectedReposFile, 'utf-8')
                .split('\n')
                .filter(Boolean);
        }

        if (!affectedRepos.includes(repo)) {
            appendFileSync(affectedReposFile, `${repo}\n`);
        }

        // 存储构建命令
        const buildCmd = getBuildCommand(repo);
        const tscCmd = getTscCommand(repo);

        const commandsTmpFile = join(cacheDir, 'commands.txt.tmp');
        if (buildCmd) {
            appendFileSync(commandsTmpFile, `${repo}:build:${buildCmd}\n`);
        }
        if (tscCmd) {
            appendFileSync(commandsTmpFile, `${repo}:tsc:${tscCmd}\n`);
        }

        // 去重命令
        if (existsSync(commandsTmpFile)) {
            const commands = readFileSync(commandsTmpFile, 'utf-8')
                .split('\n')
                .filter(Boolean);
            const uniqueCommands = Array.from(new Set(commands));
            writeFileSync(join(cacheDir, 'commands.txt'), uniqueCommands.join('\n') + '\n');
        }

        process.exit(0);
    } catch (err) {
        console.error('Error in post-tool-use-tracker hook:', err);
        process.exit(0); // 不阻塞
    }
}

main().catch(err => {
    console.error('Uncaught error:', err);
    process.exit(0);
});
