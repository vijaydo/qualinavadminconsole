<?php
defined('ABSPATH') || exit;

$root = get_option(DTU_Config::OPTION_ROOT_FOLDER, '');
?>

<div class="dtu-card">

<h2>Settings</h2>

<p style="color:#555; font-size:14px; margin-bottom:18px;">
    Configure your root Google Drive folder ID.
</p>

<form method="post" action="options.php">
    <?php settings_fields('dtu_settings'); ?>

    <table class="form-table">
        <tr>
            <th scope="row">
                <label for="dtu_root_folder_id">Root Folder ID</label>
            </th>
            <td>
                <input type="text"
                       id="dtu_root_folder_id"
                       name="<?php echo esc_attr(DTU_Config::OPTION_ROOT_FOLDER); ?>"
                       value="<?php echo esc_attr($root); ?>"
                       class="regular-text"
                       style="max-width: 400px;" />

                <p class="description">
                    Paste your Google Drive root folder ID (example: <code>0APCqjIJ3k40vUk9PVA</code>)
                </p>
            </td>
        </tr>
    </table>

    <?php submit_button('Save Changes'); ?>
</form>

</div>

<style>
.dtu-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 22px 26px;
    max-width: 650px;
    box-shadow: 0 2px 6px rgba(0,0,0,.05);
}
</style>
