# EspoCRM 报价单管理扩展 (Quote Management)

为 EspoCRM 开发的独立报价单功能模块。安装后即可使用，重装或升级 EspoCRM 时重新安装本扩展即可，无需再次开发。

An independent quotation module for EspoCRM. Install once, reinstall after EspoCRM upgrades — no re-development needed.

## 功能

- **报价单**：编号自动生成（QT-2026-0001），状态流转（草稿 → 已发送 → 已接受 / 已拒绝 / 已过期）
- **报价明细**：产品名称、规格、数量、单价、折扣，行金额自动计算
- **自动算价**：小计、折扣金额、税额、总计自动汇总，明细增删改后实时重算
- **多版本报价**：详情页「创建新版本」一键深拷贝整单（含全部明细），版本号自动 +1，并记录源自哪个版本
- **关联**：报价单可关联客户、项目；一个项目可有多个版本报价单
- **权限**：标准 ACL，可在角色管理中按"负责人/团队"控制可见范围
- **中英双语**：简体中文 + English

## 安装要求

- EspoCRM >= 10.0.0
- PHP >= 8.1

## 安装

1. 在 [Releases](../../releases) 下载 `quote-management-1.0.0.zip`
2. 进入 EspoCRM：管理 → 扩展 → 安装扩展 → 上传 zip → 安装
3. 安装后顶部导航会出现「报价单」

> 注意：如已安装 EspoCRM 官方 Sales Pack（含 Quote 实体），请勿同时安装本扩展（安装程序会自动检测并拒绝）。

## 卸载

管理 → 扩展 → 找到 Quote Management → 卸载。卸载会移除导航入口，不会删除已有报价数据表（如需彻底清理可手动删除 `quote` / `quote_item` 表）。

## 目录结构

```
manifest.json                      扩展清单
files/                             安装时复制到 EspoCRM 根目录
  custom/Espo/Modules/QuoteManagement/
    Resources/
      module.json                  模块声明
      routes.json                  API 路由
      metadata/                    实体/布局/多语言元数据
      i18n/{zh_CN,en_US}/          翻译
    Controllers/                   CRUD 控制器
    Services/QuoteService.php      编号生成、算价、版本复制
    Api/                           一键创建新版本 API
    Hooks/                         自动编号、行金额、总额重算
  client/custom/modules/quote-management/
    src/duplicate-handler.js        详情页「创建新版本」按钮
scripts/
  BeforeInstall.php                冲突检测（Sales Pack）
  AfterInstall.php                 加入导航栏
  AfterUninstall.php               移出导航栏
```

## 版本

- 1.0.0 (2026-09-29)：首版

## License

MIT
