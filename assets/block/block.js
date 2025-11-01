/**
 * andW News Changer Gutenberg Block
 */

(function() {
    'use strict';

    const { registerBlockType } = wp.blocks;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { PanelBody, SelectControl, CheckboxControl, RangeControl } = wp.components;
    const { createElement: el, Fragment } = wp.element;
    const { __ } = wp.i18n;

    registerBlockType('andw-news-changer/news-list', {
        title: __('andW News List', 'andw-news-changer'),
        description: __('andw-news投稿タイプの記事一覧を表示します', 'andw-news-changer'),
        category: 'widgets',
        icon: 'admin-page',
        keywords: [__('news', 'andw-news-changer'), __('list', 'andw-news-changer'), __('andw', 'andw-news-changer')],

        attributes: {
            layout: {
                type: 'string',
                default: ''
            },
            categories: {
                type: 'array',
                default: []
            },
            perPage: {
                type: 'number',
                default: 10
            },
            pinnedFirst: {
                type: 'boolean',
                default: false
            },
            excludeExpired: {
                type: 'boolean',
                default: false
            }
        },

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { layout, categories, perPage, pinnedFirst, excludeExpired } = attributes;

            // テンプレートオプションを取得
            const templateOptions = andwNewsBlock.templates || [];
            const categoryOptions = andwNewsBlock.categories || [];

            // useBlockPropsを使用してブロック属性を取得
            const blockProps = useBlockProps({
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
                        title: __('表示設定', 'andw-news-changer'),
                        initialOpen: true
                    },
                        el(SelectControl, {
                            label: __('レイアウト', 'andw-news-changer'),
                            value: layout,
                            options: [
                                { label: __('デフォルト', 'andw-news-changer'), value: '' },
                                ...templateOptions
                            ],
                            onChange: function(value) {
                                setAttributes({ layout: value });
                            }
                        }),

                        el(RangeControl, {
                            label: __('表示件数', 'andw-news-changer'),
                            value: perPage,
                            min: 1,
                            max: 50,
                            onChange: function(value) {
                                setAttributes({ perPage: value });
                            }
                        }),

                        el(CheckboxControl, {
                            label: __('ピン留めを先頭に表示', 'andw-news-changer'),
                            checked: pinnedFirst,
                            onChange: function(value) {
                                setAttributes({ pinnedFirst: value });
                            }
                        }),

                        el(CheckboxControl, {
                            label: __('期限切れ記事を除外', 'andw-news-changer'),
                            checked: excludeExpired,
                            onChange: function(value) {
                                setAttributes({ excludeExpired: value });
                            }
                        })
                    ),

                    categoryOptions.length > 0 && el(PanelBody, {
                        title: __('カテゴリフィルター', 'andw-news-changer'),
                        initialOpen: false
                    },
                        categoryOptions.map(function(category) {
                            return el(CheckboxControl, {
                                key: category.value,
                                label: category.label,
                                checked: categories.includes(category.value),
                                onChange: function(checked) {
                                    const newCategories = checked
                                        ? [...categories, category.value]
                                        : categories.filter(function(cat) { return cat !== category.value; });
                                    setAttributes({ categories: newCategories });
                                }
                            });
                        })
                    )
                ),

                el('div', blockProps,
                    el('div', {
                        style: {
                            fontSize: '18px',
                            marginBottom: '10px'
                        }
                    }, '📰'),
                    el('div', {
                        style: {
                            fontWeight: 'bold',
                            marginBottom: '5px'
                        }
                    }, __('andW News List', 'andw-news-changer')),
                    el('div', {
                        style: {
                            fontSize: '14px',
                            color: '#666'
                        }
                    },
                        layout
                            ? __('レイアウト: ', 'andw-news-changer') + (templateOptions.find(t => t.value === layout)?.label || layout)
                            : __('デフォルトレイアウトで表示'),
                        el('br'),
                        __('表示件数: ', 'andw-news-changer') + perPage + __('件', 'andw-news-changer'),
                        categories.length > 0 && el('span', {},
                            el('br'),
                            __('カテゴリ絞り込み: ', 'andw-news-changer') + categories.length + __('件選択', 'andw-news-changer')
                        )
                    )
                )
            );
        },

        save: function() {
            // サーバーサイドレンダリングを使用するため、saveは空
            return null;
        }
    });

})();