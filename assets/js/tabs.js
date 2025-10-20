/**
 * andW News Changer - タブ機能JavaScript
 * jQuery不要の軽量実装
 */

(function() {
    'use strict';

    /**
     * DOMが読み込まれた後に実行
     */
    function init() {
        const tabContainers = document.querySelectorAll('[data-andw-tabs]');

        tabContainers.forEach(function(container) {
            setupTabs(container);
        });
    }

    /**
     * タブ機能をセットアップ
     */
    function setupTabs(container) {
        const navItems = container.querySelectorAll('.andw-tabs__nav-item');
        const panes = container.querySelectorAll('.andw-tabs__pane');

        navItems.forEach(function(navItem, index) {
            // クリックイベント
            navItem.addEventListener('click', function() {
                switchTab(navItems, panes, index);
            });

            // キーボードイベント（アクセシビリティ対応）
            navItem.addEventListener('keydown', function(e) {
                handleKeydown(e, navItems, panes, index);
            });
        });
    }

    /**
     * タブを切り替え
     */
    function switchTab(navItems, panes, activeIndex) {
        // 全てのタブをリセット
        navItems.forEach(function(item, index) {
            item.classList.remove('andw-tabs__nav-item--active');
            item.setAttribute('aria-selected', 'false');
            item.setAttribute('tabindex', '-1');
        });

        panes.forEach(function(pane) {
            pane.classList.remove('andw-tabs__pane--active');
            pane.setAttribute('aria-hidden', 'true');
        });

        // アクティブなタブを設定
        const activeNavItem = navItems[activeIndex];
        const activePaneId = activeNavItem.getAttribute('data-tab-target');
        const activePane = document.getElementById(activePaneId);

        if (activeNavItem && activePane) {
            activeNavItem.classList.add('andw-tabs__nav-item--active');
            activeNavItem.setAttribute('aria-selected', 'true');
            activeNavItem.setAttribute('tabindex', '0');

            activePane.classList.add('andw-tabs__pane--active');
            activePane.setAttribute('aria-hidden', 'false');
        }
    }

    /**
     * キーボード操作の処理
     */
    function handleKeydown(e, navItems, panes, currentIndex) {
        let newIndex = currentIndex;

        switch (e.key) {
            case 'ArrowLeft':
                e.preventDefault();
                newIndex = currentIndex > 0 ? currentIndex - 1 : navItems.length - 1;
                break;
            case 'ArrowRight':
                e.preventDefault();
                newIndex = currentIndex < navItems.length - 1 ? currentIndex + 1 : 0;
                break;
            case 'Home':
                e.preventDefault();
                newIndex = 0;
                break;
            case 'End':
                e.preventDefault();
                newIndex = navItems.length - 1;
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                switchTab(navItems, panes, currentIndex);
                return;
            default:
                return;
        }

        // フォーカスを移動
        navItems[newIndex].focus();
        switchTab(navItems, panes, newIndex);
    }

    // DOMが読み込まれた時点で初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();