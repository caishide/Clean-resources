#!/bin/bash

echo "================================"
echo "Binary Ecom 工厂类验证脚本"
echo "================================"
echo ""

FACTORY_DIR="/www/wwwroot/binaryecom20/Files/core/database/factories"
REQUIRED_FACTORIES=(
    "AdminFactory.php"
    "DepositFactory.php"
    "WithdrawalFactory.php"
    "TransactionFactory.php"
    "OrderFactory.php"
    "BvLogFactory.php"
    "ProductFactory.php"
    "CategoryFactory.php"
)

EXISTING_FACTORIES=(
    "UserFactory.php"
    "UserExtraFactory.php"
    "WithdrawMethodFactory.php"
)

echo "检查必需的工厂类..."
echo "----------------------------------------"

for factory in "${REQUIRED_FACTORIES[@]}"; do
    if [ -f "$FACTORY_DIR/$factory" ]; then
        echo "✅ $factory"
    else
        echo "❌ $factory (缺失)"
    fi
done

echo ""
echo "检查已存在的工厂类..."
echo "----------------------------------------"

for factory in "${EXISTING_FACTORIES[@]}"; do
    if [ -f "$FACTORY_DIR/$factory" ]; then
        echo "✅ $factory"
    else
        echo "❌ $factory (缺失)"
    fi
done

echo ""
echo "检查模型文件的HasFactory特征..."
echo "----------------------------------------"

MODELS=(
    "/www/wwwroot/binaryecom20/Files/core/app/Models/Admin.php"
    "/www/wwwroot/binaryecom20/Files/core/app/Models/Product.php"
    "/www/wwwroot/binaryecom20/Files/core/app/Models/Category.php"
)

for model in "${MODELS[@]}"; do
    if grep -q "use HasFactory" "$model"; then
        echo "✅ $(basename $model) - HasFactory已添加"
    else
        echo "❌ $(basename $model) - HasFactory缺失"
    fi
done

echo ""
echo "================================"
echo "验证完成!"
echo "================================"
