<?php

if (!defined('ABSPATH')) {
    exit;
}

class KSMC_Admin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_filter('plugin_action_links_' . plugin_basename(KSMC_PLUGIN_PATH . 'kashiwazaki-seo-media-control.php'), array($this, 'add_settings_link'));
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Kashiwazaki SEO Media Control', 'kashiwazaki-seo-media-control'),
            __('Kashiwazaki SEO Media Control', 'kashiwazaki-seo-media-control'),
            'manage_options',
            'kashiwazaki-seo-media-control',
            array($this, 'admin_page'),
            'dashicons-format-gallery',
            81
        );
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=kashiwazaki-seo-media-control') . '">' . __('設定', 'kashiwazaki-seo-media-control') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_kashiwazaki-seo-media-control' !== $hook) {
            return;
        }

                wp_enqueue_style(
            'ksmc-admin-style',
            KSMC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            KSMC_VERSION . '.' . time()
        );

        wp_enqueue_script(
            'ksmc-admin-script',
            KSMC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            KSMC_VERSION . '.' . time(),
            true
        );

        wp_localize_script('ksmc-admin-script', 'ksmc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ksmc_nonce'),
            'confirm_bulk_change' => __('選択したメディアの所有者を変更しますか？', 'kashiwazaki-seo-media-control'),
            'success_message' => __('更新しました。', 'kashiwazaki-seo-media-control'),
            'error_message' => __('エラーが発生しました。', 'kashiwazaki-seo-media-control')
        ));
    }

    public function admin_page() {
        $media_manager = KSMC_Media_Manager::get_instance();
        $media_list = $media_manager->get_media_list();
        $users = get_users(array('fields' => array('ID', 'display_name')));

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

                        <div class="ksmc-controls">
                <!-- フィルタセクション -->
                <div class="ksmc-filter-section" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0;"><?php _e('フィルタ', 'kashiwazaki-seo-media-control'); ?></h3>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div>
                            <label for="ksmc-filter-owner"><?php _e('所有者でフィルタ:', 'kashiwazaki-seo-media-control'); ?></label>
                            <select id="ksmc-filter-owner">
                                <option value=""><?php _e('すべての所有者', 'kashiwazaki-seo-media-control'); ?></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="button" id="ksmc-apply-filter" class="button"><?php _e('フィルタ適用', 'kashiwazaki-seo-media-control'); ?></button>
                            <button type="button" id="ksmc-clear-filter" class="button"><?php _e('クリア', 'kashiwazaki-seo-media-control'); ?></button>
                        </div>
                        <div id="ksmc-filter-status" style="color: #666; font-style: italic;"></div>
                    </div>
                </div>

                <!-- 一括変更セクション -->
                <div class="ksmc-bulk-section" style="padding: 15px; background: #f0f8ff; border-radius: 4px;">
                    <h3 style="margin: 0 0 10px 0;"><?php _e('一括所有者変更', 'kashiwazaki-seo-media-control'); ?></h3>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div>
                            <label for="ksmc-bulk-owner"><?php _e('変更先の所有者:', 'kashiwazaki-seo-media-control'); ?></label>
                            <select id="ksmc-bulk-owner">
                                <option value=""><?php _e('所有者を選択', 'kashiwazaki-seo-media-control'); ?></option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo esc_attr($user->ID); ?>"><?php echo esc_html($user->display_name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="button" id="ksmc-bulk-change" class="button button-primary"><?php _e('選択したメディアの所有者を変更', 'kashiwazaki-seo-media-control'); ?></button>
                        </div>
                        <div>
                            <button type="button" id="ksmc-smart-change" class="button button-secondary"><?php _e('フィルタ中の全メディアを変更', 'kashiwazaki-seo-media-control'); ?></button>
                            <small style="display: block; margin-top: 5px; color: #666;">
                                <?php _e('現在表示中のメディアをすべて一括変更します', 'kashiwazaki-seo-media-control'); ?>
                            </small>
                        </div>
                    </div>

                </div>
            </div>

            <table class="wp-list-table widefat fixed striped ksmc-media-table">
                <thead>
                    <tr>
                        <td class="check-column">
                            <input type="checkbox" id="ksmc-select-all">
                        </td>
                        <th><?php _e('プレビュー', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('ファイル名', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('タイトル', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('代替テキスト', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('説明', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('キャプション', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('アップロード日', 'kashiwazaki-seo-media-control'); ?></th>
                        <th><?php _e('所有者', 'kashiwazaki-seo-media-control'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($media_list)): ?>
                        <tr>
                            <td colspan="9"><?php _e('メディアが見つかりませんでした。', 'kashiwazaki-seo-media-control'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($media_list as $media): ?>
                            <?php
                            $alt_text = get_post_meta($media->ID, '_wp_attachment_image_alt', true);
                            $author = get_userdata($media->post_author);
                            ?>
                            <tr data-media-id="<?php echo esc_attr($media->ID); ?>">
                                <th class="check-column">
                                    <input type="checkbox" class="ksmc-media-checkbox" value="<?php echo esc_attr($media->ID); ?>">
                                </th>
                                <td class="ksmc-preview">
                                    <?php if (wp_attachment_is_image($media->ID)): ?>
                                        <?php echo wp_get_attachment_image($media->ID, array(50, 50)); ?>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-media-default"></span>
                                    <?php endif; ?>
                                </td>
                                <td class="ksmc-filename">
                                    <strong><?php echo esc_html(basename(get_attached_file($media->ID))); ?></strong>
                                    <div class="row-actions">
                                        <span><a href="<?php echo esc_url(get_edit_post_link($media->ID)); ?>"><?php _e('編集', 'kashiwazaki-seo-media-control'); ?></a> | </span>
                                        <span><a href="<?php echo esc_url(wp_get_attachment_url($media->ID)); ?>" target="_blank"><?php _e('表示', 'kashiwazaki-seo-media-control'); ?></a></span>
                                    </div>
                                </td>
                                <td class="ksmc-editable" data-field="title" data-media-id="<?php echo esc_attr($media->ID); ?>">
                                    <span class="ksmc-display"><?php echo esc_html($media->post_title); ?></span>
                                    <input type="text" class="ksmc-input" value="<?php echo esc_attr($media->post_title); ?>" style="display: none;">
                                </td>
                                <td class="ksmc-editable" data-field="alt_text" data-media-id="<?php echo esc_attr($media->ID); ?>">
                                    <span class="ksmc-display"><?php echo esc_html($alt_text); ?></span>
                                    <input type="text" class="ksmc-input" value="<?php echo esc_attr($alt_text); ?>" style="display: none;">
                                </td>
                                <td class="ksmc-editable" data-field="description" data-media-id="<?php echo esc_attr($media->ID); ?>">
                                    <span class="ksmc-display"><?php echo esc_html($media->post_content); ?></span>
                                    <textarea class="ksmc-input" style="display: none;"><?php echo esc_textarea($media->post_content); ?></textarea>
                                </td>
                                <td class="ksmc-editable" data-field="caption" data-media-id="<?php echo esc_attr($media->ID); ?>">
                                    <span class="ksmc-display"><?php echo esc_html($media->post_excerpt); ?></span>
                                    <input type="text" class="ksmc-input" value="<?php echo esc_attr($media->post_excerpt); ?>" style="display: none;">
                                </td>
                                <td class="ksmc-editable" data-field="upload_date" data-media-id="<?php echo esc_attr($media->ID); ?>">
                                    <span class="ksmc-display"><?php echo esc_html(get_the_date('Y-m-d H:i:s', $media->ID)); ?></span>
                                    <input type="datetime-local" class="ksmc-input" value="<?php echo esc_attr(get_the_date('Y-m-d\TH:i:s', $media->ID)); ?>" style="display: none;">
                                </td>
                                <td>
                                    <select class="ksmc-owner-dropdown" data-media-id="<?php echo esc_attr($media->ID); ?>">
                                        <?php foreach ($users as $user): ?>
                                            <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($media->post_author, $user->ID); ?>>
                                                <?php echo esc_html($user->display_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
