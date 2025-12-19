# 代码修复对比

## 1. AdminController.php 修复对比

### 修复前 (行 325 & 350)
```php
return to_route('admin.dashboard')->withErrors('Something went wrong');
```

### 修复后 (行 325 & 350)
```php
return to_route('admin.dashboard')->withErrors(__('admin.error.something_wrong'));
```

---

## 2. bonus_config.blade.php 修复对比

### 修复前 (行 85-93)
```php
<ul class="list-unstyled mb-0 small">
    <li class="d-flex justify-content-between mb-2"><span>版本</span><span>{{ $config['version'] ?? 'v10.1' }}</span></li>
    <li class="d-flex justify-content-between mb-2"><span>直推奖</span><span>{{ ($config['direct_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>层碰奖</span><span>{{ ($config['level_pair_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>对碰单对</span><span>{{ ($config['pair_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>管理奖 1-3代</span><span>{{ ($config['management_rates']['1-3'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>管理奖 4-5代</span><span>{{ ($config['management_rates']['4-5'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>周封顶 L1/L2/L3</span><span>{{ ($config['pair_cap'][1] ?? 0) }} / {{ ($config['pair_cap'][2] ?? 0) }} / {{ ($config['pair_cap'][3] ?? 0) }}</span></li>
    <li class="d-flex justify-content-between mb-2"><span>功德池</span><span>{{ ($config['global_reserve_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>季度池(消费商/领导)</span><span>{{ ($config['pool_stockist_rate'] ?? 0) * 100 }}% / {{ ($config['pool_leader_rate'] ?? 0) * 100 }}%</span></li>
</ul>
```

### 修复后 (行 85-93)
```php
<ul class="list-unstyled mb-0 small">
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.version')</span><span>{{ $config['version'] ?? 'v10.1' }}</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.direct_commission')</span><span>{{ ($config['direct_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.level_pair_commission')</span><span>{{ ($config['level_pair_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.pair_commission')</span><span>{{ ($config['pair_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.management_commission') 1-3代</span><span>{{ ($config['management_rates']['1-3'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.management_commission') 4-5代</span><span>{{ ($config['management_rates']['4-5'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.weekly_cap') L1/L2/L3</span><span>{{ ($config['pair_cap'][1] ?? 0) }} / {{ ($config['pair_cap'][2] ?? 0) }} / {{ ($config['pair_cap'][3] ?? 0) }}</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.global_reserve')</span><span>{{ ($config['global_reserve_rate'] ?? 0) * 100 }}%</span></li>
    <li class="d-flex justify-content-between mb-2"><span>@lang('admin.bonus.quarterly_pool')(消费商/领导)</span><span>{{ ($config['pool_stockist_rate'] ?? 0) * 100 }}% / {{ ($config['pool_leader_rate'] ?? 0) * 100 }}%</span></li>
</ul>
```

---

## 3. edit.blade.php 修复对比

### 修复前 (行 367-372 & 409)
```php
@push('script')
    <script>
        (function($) {
            "use strict";
```
```javascript
                    } catch (err) {
                        alert('Please press Ctrl/Cmd + C to copy');
                    }
```

### 修复后 (行 367-372 & 411-412)
```php
@push('script')
    <script>
        @php
            $copyInstruction = __('admin.gateway.copy_instruction');
        @endphp
        (function($) {
            "use strict";
```
```javascript
                    } catch (err) {
                        alert(@json($copyInstruction));
                    }
```

---

## 主要变化

### 翻译键命名约定
- 使用点分隔符命名空间: `admin.category.key`
- 分类清晰: `admin.error.*`, `admin.bonus.*`, `admin.gateway.*`

### 函数使用
- PHP: `__('key')` 用于获取翻译
- Blade: `@lang('key')` 用于输出翻译
- JavaScript: `@json($var)` 用于传递PHP变量到JS

### 翻译键层次
```
admin/
├── error/
│   └── something_wrong
├── bonus/
│   ├── version
│   ├── direct_commission
│   ├── level_pair_commission
│   ├── pair_commission
│   ├── management_commission
│   ├── weekly_cap
│   ├── global_reserve
│   └── quarterly_pool
└── gateway/
    └── copy_instruction
```
