# itcry-woo-pay
一个为WooCommerce重构和修复的免签约支付插件，支持码支付(Codepay)和易支付(Easypay)。网站：https://itcry.com
# ITCRY WOOPAY (重构安全版)

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![WordPress Requires at least](https://img.shields.io/badge/WordPress->=5.0-blue.svg)](https://wordpress.org/download/)
[![WC Requires at least](https://img.shields.io/badge/WooCommerce->=5.0-blue.svg)](https://woocommerce.com/)

这是一个为 WooCommerce 重构和深度修复的免签约支付插件，旨在提供一个更安全、更稳定、更易用的支付解决方案。它支持 **码支付 (Codepay)** 和 **易支付 (Easypay)** 两大主流免签约支付接口，可以在生产环境中稳定运行。

---

## ✨ 功能特性

* **双支付接口支持**:
    * **码支付 (Codepay)**: 支持 支付宝、微信支付、QQ钱包。
    * **易支付 (Easypay)**: 支持 支付宝、微信支付、QQ钱包。
* **独立的设置面板**: 在WordPress后台提供统一的 "ITCRY支付" 设置入口，分标签页管理不同接口的配置，清晰直观。
* **增强的安全性**:
    * 所有支付通知均通过严格的签名 (Signature) 校验，杜绝伪造回调。
    * 对后台设置的输入和输出进行了安全过滤，防止跨站脚本攻击 (XSS)。
    * 修复了码支付接口存在的金额校验漏洞。
* **完善的跳转逻辑**:
    * **[重大修复]** 彻底解决了支付成功后跳转到错误页面或导致 `404 Not Found` 的问题。
    * **[功能增强]** 支持自定义支付成功后的跳转页面。
    * **[智能跳转]** 当自定义跳转地址**留空**时，会自动跳转到WooCommerce标准的**订单详情页**，对销售虚拟自动发货产品的网站体验极佳。
* **现代化的代码结构**:
    * 采用面向对象 (OOP) 的方式重构了整个插件，代码结构清晰，易于维护和二次开发。
    * 遵循WordPress和WooCommerce的开发最佳实践。
    * 现已经支持PHP8.0+

## ⚙️ 安装方法

您可以通过以下两种方式安装本插件：

#### 方式一：通过WordPress后台上传 (推荐)

1.  前往本项目的 [GitHub Releases](https://github.com/16188/itcry-woo-pay/releases) 页面。
2.  下载最新的 `itcry-woo-pay.zip` 发行包。
3.  登录您的WordPress后台，导航至 `插件` > `安装插件`。
4.  点击顶部的 `上传插件` 按钮，选择刚刚下载的zip文件并上传。
5.  安装成功后，点击 `启用插件`。

#### 方式二：通过FTP手动上传

1.  下载本项目仓库的zip包并解压。
2.  使用FTP客户端，将解压后的整个 `itcry-woo-pay` 文件夹上传到您网站的 `/wp-content/plugins/` 目录下。
3.  登录您的WordPress后台，导航至 `插件` > `已安装的插件`，找到 "ITCRY WOOPAY" 并启用它。

## 🛠️ 配置步骤

插件启用后，您需要配置支付接口信息才能正常收款。

1.  在WordPress后台，点击左侧菜单的 “**ITCRY支付**”。
2.  您会看到 **码支付 (Codepay)** 和 **易支付 (Easypay)** 两个标签页。请选择您需要使用的支付接口。
3.  **填写API信息**：
    * **对于易支付**: 您需要填写 `支付网关地址`、`商户ID` 和 `商户密钥`。
    * **对于码支付**: 您需要填写 `码支付ID` 和 `通讯密钥`。
4.  **配置跳转地址 (重要)**：
    * **自定义同步跳转地址**: 这是一个选填项。
        * 如果您**填写**了一个URL（例如，您自定义的感谢页面），支付成功后用户将跳转到此地址。
        * 如果您将此项**留空**，支付成功后用户将自动跳转到该笔订单的**订单详情页**，非常适合虚拟物品销售。
5.  **保存设置**：点击 `Save Settings` 按钮。
6.  **启用支付网关**：
    * 前往 `WooCommerce` > `设置` > `付款` 标签页。
    * 找到您想启用的支付方式（例如“易支付 - 微信支付”），打开开关并保存。

现在，您的网站已经可以通过配置好的接口进行收款了！

## 📸 截图预览

**后台设置页面:**
![后台设置截图](https://sc04.alicdn.com/kf/H86abd9c9d96c467ca48fd27bb61674e1J/231863025/H86abd9c9d96c467ca48fd27bb61674e1J.jpg)

## 📄 许可证

本项目基于 **GPL-3.0 License** 开源。详情请参阅 `LICENSE` 文件。
