# 领域专家 Agents

本目录包含专业领域的专家 Agent，为特定领域提供深度的专业知识和算法验证。

## 🎯 Agent 列表

### 1. bazi-expert (八字命理专家)
**文件**: `bazi-expert.md`  
**专长**: 四柱八字、五行生克、十神、格局、大运流年

**核心能力**:
- 精确的农历转换和节气计算
- 四柱排盘算法设计和验证
- 五行力量分析
- 格局判定和用神选取
- 大运流年计算
- 神煞系统
- 算法准确性验证

**何时调用**:
- 需要八字计算算法设计
- 验证命理算法准确性
- 解决八字相关专业问题
- 生成八字分析报告
- 边界条件处理（子时、节气交界等）

**示例调用**:
```typescript
// 在工作流中
"请 bazi-expert agent 验证四柱计算算法的准确性"
"需要 bazi-expert 设计五行力量分析算法"
"咨询 bazi-expert 关于节气交界的处理方案"
```

---

### 2. fengshui-expert (风水专家)
**文件**: `fengshui-expert.md`  
**专长**: 玄空飞星、八宅风水、罗盘定向、形势风水

**核心能力**:
- 玄空飞星计算和排盘
- 八宅命卦和吉凶方位
- 二十四山罗盘定向
- 风水布局分析
- 形煞识别和化解
- 择日风水
- 商业和住宅风水

**何时调用**:
- 需要风水算法设计
- 玄空飞星排盘计算
- 八宅分析实现
- 罗盘方位计算
- 布局优化建议
- 风水报告生成

**示例调用**:
```typescript
// 在工作流中
"请 fengshui-expert agent 设计玄空飞星算法"
"需要 fengshui-expert 分析住宅风水布局"
"咨询 fengshui-expert 关于罗盘定向的实现方案"
```

---

### 3. metaphysics-consultant (通用玄学顾问)
**文件**: `metaphysics-consultant.md`  
**专长**: 综合玄学咨询、跨领域整合、易经占卜、择日、姓名学

**核心能力**:
- 跨领域协调和整合
- 易经六十四卦系统
- 综合择日服务
- 姓名学分析
- 生肖配对
- 紫微斗数
- 数字能量学
- 多系统综合分析

**何时调用**:
- 需要多领域综合分析
- 协调 bazi-expert 和 fengshui-expert
- 易经、择日、姓名等通用功能
- 生成综合性分析报告
- 需要跨领域验证

**示例调用**:
```typescript
// 在工作流中
"请 metaphysics-consultant 协调八字和风水分析"
"需要 metaphysics-consultant 提供择日建议"
"咨询 metaphysics-consultant 进行姓名分析"
```

---

## 🔄 Agent 协作模式

### 模式 1: 单一专家咨询
```
用户需求 → 特定领域 Agent → 专业分析 → 结果输出
```

**示例**: 用户只需要八字分析
```
需求: "优化八字计算算法"
  ↓
bazi-expert Agent
  ↓
算法设计和验证
  ↓
实施方案
```

### 模式 2: 多专家协同
```
用户需求 → metaphysics-consultant → 调度多个专家 → 整合结果 → 综合报告
```

**示例**: 用户需要综合命理和风水分析
```
需求: "设计生肖配对功能，结合八字和风水"
  ↓
metaphysics-consultant (协调者)
  ├─→ bazi-expert (八字部分)
  └─→ fengshui-expert (风水部分)
  ↓
整合分析结果
  ↓
综合实施方案
```

### 模式 3: 专家交叉验证
```
算法实现 → Agent A 验证 → Agent B 复核 → 确认正确性
```

**示例**: 算法准确性验证
```
四柱算法实现
  ↓
bazi-expert 专业验证
  ↓
metaphysics-consultant 综合验证
  ↓
确认准确性
```

---

## 📋 使用指南

### 1. 需求分析阶段
```text
识别需求类型:
- 纯八字问题 → bazi-expert
- 纯风水问题 → fengshui-expert  
- 综合或其他 → metaphysics-consultant
```

### 2. 技术评审阶段
```text
专业验证:
- 八字算法 → bazi-expert 审查
- 风水算法 → fengshui-expert 审查
- 综合方案 → metaphysics-consultant 整合审查
```

### 3. 代码实现阶段
```text
算法咨询:
- 需要专业算法 → 咨询对应 Agent
- 边界条件处理 → 咨询对应 Agent
- 测试用例设计 → 咨询对应 Agent
```

---

## 🎨 实际应用场景

### 场景 1: 八字分析功能开发
```yaml
阶段0-需求理解:
  主导: fullstack-developer
  咨询: bazi-expert (理解八字专业需求)

阶段1-需求文档:
  主导: fullstack-developer
  评审: bazi-expert (验证专业准确性)

阶段2-技术方案:
  主导: architect-reviewer
  协助: bazi-expert (算法设计)
  
阶段5-代码实现:
  主导: fullstack-developer
  咨询: bazi-expert (算法验证)

阶段7-测试验证:
  主导: qa-expert
  验证: bazi-expert (专业准确性)
```

### 场景 2: 风水分析功能开发
```yaml
阶段0-需求理解:
  主导: fullstack-developer
  咨询: fengshui-expert (理解风水专业需求)

阶段1-需求文档:
  主导: fullstack-developer
  评审: fengshui-expert (验证专业准确性)

阶段2-技术方案:
  主导: architect-reviewer
  协助: fengshui-expert (算法设计)
  
阶段5-代码实现:
  主导: fullstack-developer
  咨询: fengshui-expert (算法验证)
```

### 场景 3: 综合功能开发（如生肖配对）
```yaml
阶段0-需求理解:
  主导: fullstack-developer
  咨询: metaphysics-consultant (整体规划)

阶段1-需求文档:
  主导: fullstack-developer
  评审: metaphysics-consultant (综合验证)
  
阶段2-技术方案:
  主导: architect-reviewer
  协助: 
    - metaphysics-consultant (整体协调)
    - bazi-expert (命理部分)
    - fengshui-expert (风水部分，如需要)
```

---

## 💡 最佳实践

### 1. 明确调用时机
- ✅ 需要专业算法设计时调用
- ✅ 验证算法准确性时调用
- ✅ 解决专业难题时调用
- ❌ 不要用于一般的代码编写
- ❌ 不要用于 UI/UX 设计

### 2. 清晰的沟通
```text
好的调用方式:
"请 bazi-expert 验证四柱计算算法，特别关注节气交界和子时处理"

不好的调用方式:
"帮我写八字功能"  // 太笼统
```

### 3. 充分利用专长
```text
八字相关问题:
- 农历转换 → bazi-expert
- 节气计算 → bazi-expert
- 五行分析 → bazi-expert

风水相关问题:
- 飞星排盘 → fengshui-expert
- 罗盘定向 → fengshui-expert
- 布局优化 → fengshui-expert

综合问题:
- 跨领域分析 → metaphysics-consultant
- 择日服务 → metaphysics-consultant
- 姓名分析 → metaphysics-consultant
```

### 4. 测试用例设计
```typescript
// 咨询专家 Agent 获取测试用例
"请 bazi-expert 提供八字计算的边界测试用例"

// Agent 会提供:
test_cases = [
  "1990-02-04 00:30", // 节气交界
  "1985-02-04 23:55", // 子时边界
  "2000-02-29 12:00", // 闰年
  // ... 更多专业测试用例
]
```

---

## 🔍 工具和资源

### 领域专家 Agent 可用工具
```yaml
共同工具:
  - Read/Write: 读写文件
  - python: 算法实现
  - context7: 查询专业知识
  - research: 深入研究

bazi-expert 特有:
  - lunar-javascript 库
  - sxtwl 库
  
fengshui-expert 特有:
  - browser_navigate: 查看地图
  - 罗盘计算工具
  
metaphysics-consultant:
  - 协调其他 Agent
  - 综合分析工具
```

### 经典文献参考
参见各 Agent 文件中的 "Classical References" 部分

---

## 📊 质量标准

### 准确性要求
- 八字计算精度: 节气精确到分钟
- 风水方位精度: 精确到度
- 算法性能: < 100ms

### 专业性要求
- 遵循经典理论
- 避免迷信表述
- 提供科学解释
- 保护用户隐私

### 代码质量
- 测试覆盖率 > 90%
- 清晰的注释
- 完善的错误处理
- 边界条件处理

---

## 🚀 快速开始

### 调用示例

```text
# 场景 1: 开发八字功能
您: "我需要开发八字分析功能，请帮我设计算法"

AI: 我将调用 bazi-expert Agent 来协助...
    [bazi-expert 提供专业的算法设计方案]

# 场景 2: 验证算法
您: "请验证这个四柱计算算法的准确性"

AI: 我将请 bazi-expert 进行专业验证...
    [bazi-expert 进行详细的验证和反馈]

# 场景 3: 综合咨询
您: "设计一个综合命理和风水的功能"

AI: 我将调用 metaphysics-consultant 协调多个专家...
    [metaphysics-consultant 协调 bazi-expert 和 fengshui-expert]
```

---

## 📞 联系和支持

如果您在使用这些领域专家 Agent 时遇到问题:
1. 检查调用方式是否正确
2. 确认问题是否属于该 Agent 的专业领域
3. 查看 Agent 文件中的详细说明
4. 尝试使用 metaphysics-consultant 进行协调

---

**版本**: v1.0.0  
**创建日期**: 2025-01-13  
**维护者**: QiFlow AI QiFlow AI Team
