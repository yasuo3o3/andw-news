/**
 * andW News — ブロックエディタ
 */
(function() {
    'use strict';

    var registerBlockType = wp.blocks.registerBlockType;
    var InspectorControls = wp.blockEditor.InspectorControls;
    var useBlockProps      = wp.blockEditor.useBlockProps;
    var PanelBody          = wp.components.PanelBody;
    var RangeControl       = wp.components.RangeControl;
    var ToggleControl      = wp.components.ToggleControl;
    var CheckboxControl    = wp.components.CheckboxControl;
    var el                 = wp.element.createElement;
    var Fragment           = wp.element.Fragment;
    var __                 = wp.i18n.__;

    registerBlockType('andw-news/latest-posts', {
        edit: function(props) {
            var attributes    = props.attributes;
            var setAttributes = props.setAttributes;
            var perPage        = attributes.perPage;
            var categories     = attributes.categories;
            var showCategories = attributes.showCategories;
            var showTabs       = attributes.showTabs;
            var pinnedFirst    = attributes.pinnedFirst;

            var categoryOptions = (andwNewsBlock && andwNewsBlock.categories) || [];

            var blockProps = useBlockProps({
                className: 'andw-news-block-placeholder',
                style: {
                    padding: '20px',
                    border: '2px dashed #ccc',
                    textAlign: 'center',
                    backgroundColor: '#f9f9f9'
                }
            });

            return el(Fragment, {},
                el(InspectorControls, {},
                    el(PanelBody, {
                        title: __('表示設定', 'andw-news'),
                        initialOpen: true
                    },
                        el(RangeControl, {
                            label: __('表示件数', 'andw-news'),
                            value: perPage,
                            min: 1,
                            max: 50,
                            onChange: function(value) {
                                setAttributes({ perPage: value });
                            }
                        }),

                        el(ToggleControl, {
                            label: __('カテゴリーバッジを表示', 'andw-news'),
                            checked: showCategories,
                            onChange: function(value) {
                                setAttributes({ showCategories: value });
                            }
                        }),

                        el(ToggleControl, {
                            label: __('タブ切り替え', 'andw-news'),
                            help: showTabs
                                ? __('カテゴリー別タブで表示します', 'andw-news')
                                : __('フラットなリストで表示します', 'andw-news'),
                            checked: showTabs,
                            onChange: function(value) {
                                setAttributes({ showTabs: value });
                            }
                        }),

                        el(ToggleControl, {
                            label: __('ピン留め優先', 'andw-news'),
                            checked: pinnedFirst,
                            onChange: function(value) {
                                setAttributes({ pinnedFirst: value });
                            }
                        })
                    ),

                    categoryOptions.length > 0 && el(PanelBody, {
                        title: __('カテゴリー絞り込み', 'andw-news'),
                        initialOpen: false
                    },
                        categoryOptions.map(function(category) {
                            return el(CheckboxControl, {
                                key: category.value,
                                label: category.label,
                                checked: categories.indexOf(category.value) !== -1,
                                onChange: function(checked) {
                                    var newCategories = checked
                                        ? categories.concat([category.value])
                                        : categories.filter(function(cat) { return cat !== category.value; });
                                    setAttributes({ categories: newCategories });
                                }
                            });
                        })
                    )
                ),

                el('div', blockProps,
                    el('div', { style: { fontWeight: 'bold', marginBottom: '5px' } },
                        __('andW News', 'andw-news')
                    ),
                    el('div', { style: { fontSize: '14px', color: '#666' } },
                        showTabs ? __('タブ表示', 'andw-news') : __('リスト表示', 'andw-news'),
                        ' / ',
                        perPage + __('件', 'andw-news'),
                        categories.length > 0 && el('span', {},
                            ' / ',
                            categories.length + __('カテゴリ選択', 'andw-news')
                        )
                    )
                )
            );
        },

        save: function() {
            return null;
        }
    });
})();
