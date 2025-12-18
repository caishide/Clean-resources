#!/usr/bin/env node
import { readFileSync, existsSync } from 'fs';
import { join } from 'path';

interface HookInput {
    session_id: string;
    transcript_path: string;
    cwd: string;
    permission_mode: string;
    hook_event_name: string;
}

interface EditedFile {
    path: string;
    tool: string;
    timestamp: string;
}

interface SessionTracking {
    edited_files: EditedFile[];
}

function getFileCategory(filePath: string): 'backend' | 'frontend' | 'database' | 'other' {
    const normalized = filePath.replace(/\\/g, '/');

    // Frontend detection：Next.js App Router 页面、通用组件、AI UI 等
    if (normalized.includes('/src/app/') ||
        normalized.includes('/components/') ||
        normalized.includes('/src/ai/') ||
        normalized.includes('/frontend/') ||
        normalized.includes('/client/') ||
        normalized.includes('/src/features/')) {
        return 'frontend';
    }

    // Backend detection：API 路由、server actions、后端 lib 等
    if (normalized.includes('/src/app/api/') ||
        normalized.includes('/src/actions/') ||
        normalized.includes('/src/lib/') ||
        normalized.includes('/src/server/') ||
        normalized.includes('/server/')) {
        return 'backend';
    }

    // Database / 持久化层
    if (normalized.includes('/drizzle/') ||
        normalized.includes('/src/db/') ||
        normalized.includes('/database/') ||
        normalized.includes('/prisma/') ||
        normalized.includes('/migrations/')) {
        return 'database';
    }

    return 'other';
}

function shouldCheckErrorHandling(filePath: string): boolean {
    const normalized = filePath.replace(/\\/g, '/');

    // Skip test files, config files, type definitions 以及脚本/测试目录
    if (normalized.match(/\.(test|spec)\.(ts|tsx)$/)) return false;
    if (normalized.match(/\.(config|d)\.(ts|tsx)$/)) return false;
    if (normalized.includes('types/')) return false;
    if (normalized.includes('.styles.ts')) return false;
    if (normalized.includes('/scripts/')) return false;
    if (normalized.includes('/tests/') || normalized.includes('__tests__')) return false;

    // 只关注源码文件
    return normalized.match(/\.(ts|tsx|js|jsx)$/) !== null;
}

function analyzeFileContent(filePath: string): {
    hasTryCatch: boolean;
    hasAsync: boolean;
    hasPrisma: boolean; // 在本项目中表示“数据库 / 领域错误模型 / 外部服务调用”
    hasController: boolean;
    hasApiCall: boolean;
} {
    if (!existsSync(filePath)) {
        return {
            hasTryCatch: false,
            hasAsync: false,
            hasPrisma: false,
            hasController: false,
            hasApiCall: false,
        };
    }

    const content = readFileSync(filePath, 'utf-8');

    return {
        hasTryCatch: /try\s*\{/.test(content),
        hasAsync: /async\s+/.test(content),
        // 数据库 / 统一错误模型 / 外部服务集成（drizzle、ErrorCode、QiFlowError 等）
        hasPrisma: (/from ['"]drizzle-orm['"]|drizzle|db\./i.test(content) ||
            /ErrorCode\.|QiFlowError|createErrorResponse|createError\./.test(content)),
        // API 路由 / Next.js Route Handlers / server actions
        hasController: (/export async function (GET|POST|PUT|DELETE|PATCH)/.test(content) ||
            /NextResponse\.json|RouteHandlerContext/.test(content)),
        // 前端 / 客户端 API 调用
        hasApiCall: /fetch\(|axios\.|apiClient\.|useSWR\(/i.test(content),
    };
}

async function main() {
    try {
        // Read input from stdin
        const input = readFileSync(0, 'utf-8');
        const data: HookInput = JSON.parse(input);

        const { session_id } = data;
        const projectDir = process.env.CLAUDE_PROJECT_DIR || process.cwd();

        // 允许通过环境变量关闭错误提醒
        if (process.env.SKIP_ERROR_REMINDER === '1') {
            process.exit(0);
        }

        // Check for edited files tracking（统一使用项目下的 .claude/tsc-cache）
        const cacheDir = join(projectDir, '.claude', 'tsc-cache', session_id);
        const trackingFile = join(cacheDir, 'edited-files.log');

        if (!existsSync(trackingFile)) {
            // No files edited this session, no reminder needed
            process.exit(0);
        }

        // Read tracking data
        const trackingContent = readFileSync(trackingFile, 'utf-8');
        const editedFiles = trackingContent
            .trim()
            .split('\n')
            .filter(line => line.length > 0)
            .map(line => {
                // 新格式：timestamp<TAB>tool<TAB>path
                if (line.includes('\t')) {
                    const [timestamp, tool, path] = line.split('\t');
                    return { timestamp, tool, path };
                }

                // 兼容旧格式：timestamp:filePath:repo（中间部分可能包含 Windows 盘符冒号）
                const firstColon = line.indexOf(':');
                const lastColon = line.lastIndexOf(':');

                if (firstColon === -1 || lastColon === -1 || firstColon === lastColon) {
                    return null;
                }

                const timestamp = line.slice(0, firstColon);
                const path = line.slice(firstColon + 1, lastColon);

                return { timestamp, tool: 'Edit', path };
            })
            .filter((entry): entry is EditedFile => Boolean(entry && entry.path));

        if (editedFiles.length === 0) {
            process.exit(0);
        }

        // Categorize files
        const categories = {
            backend: [] as string[],
            frontend: [] as string[],
            database: [] as string[],
            other: [] as string[],
        };

        const analysisResults: Array<{
            path: string;
            category: string;
            analysis: ReturnType<typeof analyzeFileContent>;
        }> = [];

        for (const file of editedFiles) {
            if (!shouldCheckErrorHandling(file.path)) continue;

            const category = getFileCategory(file.path);
            categories[category].push(file.path);

            const analysis = analyzeFileContent(file.path);
            analysisResults.push({ path: file.path, category, analysis });
        }

        // Check if any code that needs error handling was written
        const needsAttention = analysisResults.some(
            ({ analysis }) =>
                analysis.hasTryCatch ||
                analysis.hasAsync ||
                analysis.hasPrisma ||
                analysis.hasController ||
                analysis.hasApiCall
        );

        if (!needsAttention) {
            // No risky code patterns detected, skip reminder
            process.exit(0);
        }

        // Display reminder
        console.log('\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('📋 ERROR HANDLING SELF-CHECK');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

        // Backend reminders
        if (categories.backend.length > 0) {
            const backendFiles = analysisResults.filter(f => f.category === 'backend');
            const hasTryCatch = backendFiles.some(f => f.analysis.hasTryCatch);
            const hasPrisma = backendFiles.some(f => f.analysis.hasPrisma);
            const hasController = backendFiles.some(f => f.analysis.hasController);

            console.log('⚠️  Backend Changes Detected');
            console.log(`   ${categories.backend.length} file(s) edited\n`);

            if (hasTryCatch) {
                console.log('   ❓ catch 块里有使用 logError() 或 captureException() 记录错误吗？');
            }
            if (hasPrisma) {
                console.log('   ❓ 使用 Drizzle / 数据库 / 外部服务时，有统一转换为 QiFlowError / ErrorCode 吗？');
            }
            if (hasController) {
                console.log('   ❓ API 路由是否通过 createErrorResponse()/toErrorResponse() 统一返回错误？');
            }

            console.log('\n   💡 Backend Best Practice:');
            console.log('      - 业务错误优先使用 QiFlowError / createError 工厂函数');
            console.log('      - API 路由统一使用 Zod 校验 + createErrorResponse + logError');
            console.log('      - 严重错误使用 Sentry.captureException 或 captureException() 上报\n');
        }

        // Frontend reminders
        if (categories.frontend.length > 0) {
            const frontendFiles = analysisResults.filter(f => f.category === 'frontend');
            const hasApiCall = frontendFiles.some(f => f.analysis.hasApiCall);
            const hasTryCatch = frontendFiles.some(f => f.analysis.hasTryCatch);

            console.log('💡 Frontend Changes Detected');
            console.log(`   ${categories.frontend.length} file(s) edited\n`);

            if (hasApiCall) {
                console.log('   ❓ 调用后端 / AI 接口时，有把错误转换为用户可读文案吗？');
            }
            if (hasTryCatch) {
                console.log('   ❓ 是否使用 getUserFriendlyMessage()/getErrorMessage() 统一处理错误？');
            }

            console.log('\n   💡 Frontend Best Practice:');
            console.log('      - 使用统一的错误展示组件（如 ErrorDisplay / ErrorBoundary）');
            console.log('      - 网络 / 认证 / 积分不足等场景给出清晰提示');
            console.log('      - 避免在前端日志中泄露敏感信息（token、邮箱等）\n');
        }

        // Database reminders
        if (categories.database.length > 0) {
            console.log('🗄️  Database Changes Detected');
            console.log(`   ${categories.database.length} file(s) edited\n`);
            console.log('   ❓ Drizzle 表/列名与 schema 是否一致？');
            console.log('   ❓ migrations 是否在本地和预发环境验证过？\n');
        }

        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        console.log('💡 TIP: Disable with SKIP_ERROR_REMINDER=1');
        console.log('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n');

        process.exit(0);
    } catch (err) {
        // Silently fail - this is just a reminder, not critical
        process.exit(0);
    }
}

main().catch(() => process.exit(0));
