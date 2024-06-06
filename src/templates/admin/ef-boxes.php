<?php
global $wpdb;

// Get the boxes from the database
$boxes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}pagbank_ef_boxes");
?>
<button id="add-box">Add Box</button>
// Display the boxes in a table
<table class="wc-shipping-zone-methods widefat">
    <thead>
    <tr>
        <th class="wc-shipping-zone-method-sort">ID</th>
        <th class="wc-shipping-zone-method-title"><?php _e('Referência', 'pagbank-connect')?></th>
        <th class="wc-shipping-zone-method-title"><?php _e('Largura', 'pagbank-connect')?></th>
        <th class="wc-shipping-zone-method-title"><?php _e('Comprimento', 'pagbank-connect')?></th>
        <th class="wc-shipping-zone-method-title"><?php _e('Altura/Profundidade', 'pagbank-connect')?></th>
        <th class="wc-shipping-zone-method-title"><?php _e('Peso máximo', 'pagbank-connect')?></th>
        <th class="wc-shipping-zone-method-enabled"><?php _e('Disponível', 'pagbank-connect')?></th>
        <th class=""></th>
    </tr>
    </thead>
    <tbody class="wc-shipping-zone-methods">
<?php
foreach ($boxes as $box) {
    echo '<tr data-id="' . $box->box_id . '" data-json="' . htmlentities(json_encode($box)) . '">';
    echo '<td>'.$box->box_id.'</td>';
    echo '<td>'.$box->reference.'</td>';
    echo '<td>'.$box->outer_width.'</td>';
    echo '<td>'.$box->outer_length.'</td>';
    echo '<td>'.$box->outer_depth.'</td>';
    echo '<td>'.$box->max_weight.'</td>';
    echo '<td>'.($box->is_available ? 'Yes' : 'No').'</td>';
    echo '<td class="wc-shipping-zone-actions">
			<div>
				<a class="wc-shipping-zone-method-settings wc-shipping-zone-action-edit" href="">Editar</a> | <a href="#" class="wc-shipping-zone-method-delete wc-shipping-zone-actions">Excluir</a>
			</div>
		</td>';
    echo '</tr>';
}
?>
    </tbody>
</table>
<script type="text/template" id="tmpl-box-form-modal">
    <div class="wc-backbone-modal wc-backbone-modal-shipping-method-settings">
        <div class="wc-backbone-modal-content">
            <section class="wc-backbone-modal-main" role="main">
                <header class="wc-backbone-modal-header">
                    <h1><?php echo __('Add/Edit Box', 'pagbank-connect') ?></h1>
                    <button class="modal-close modal-close-link dashicons dashicons-no-alt">
                        <span class="screen-reader-text">Close modal panel</span>
                    </button>
                </header>
                <article>
                    <form id="box-form">
                        <div class="wc-shipping-zone-method-fields">
                            <input type="hidden" id="box-id" name="box-id">
                            <fieldset>
                                <legend class="screen-reader-text"><span>Reference</span></legend>
                                <label for="box-reference"><?php _e('Referência', 'pagbank-connect')?>:</label>
                                <input type="text" id="box-reference" name="box-reference" maxlength="30" placeholder="<?php _e('Ex: Caixa Correios Grande G08', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Width</span></legend>
                                <label for="box-width"><?php _e('Largura', 'pagbank-connect')?>:</label>
                                <input type="tel" min="10" max="100" id="box-width" name="box-width" placeholder="cm" required title="<?php _e('Mínimo 10 / Máximo 100', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Height</span></legend>
                                <label for="box-height"><?php _e('Altura/Profundidade', 'pagbank-connect')?>:</label>
                                <input type="tel" min="1" max="100" id="box-height" name="box-height" placeholder="cm" required title="<?php _e('Mínimo 1 / Máximo 100', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Length</span></legend>
                                <label for="box-length"><?php _e('Comprimento', 'pagbank-connect')?>:</label>
                                <input type="tel" min="15" max="100" id="box-length" name="box-length" placeholder="cm" required title="<?php _e('Mínimo 15 / Máximo 100', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Espessura</span></legend>
                                <label for="box-thickness"><?php _e('Espessura', 'pagbank-connect')?>:</label>
                                <input type="text" id="box-thickness" name="box-thickness" placeholder="cm" required title="<?php _e('Informe a espessura de um dos lados. Ele será usado em conjunto com os outros campos para calcular o tamanho interno da embalagem em cada dimensão.', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Empty Weight</span></legend>
                                <label for="box-empty_weight"><?php _e('Peso da embalagem', 'pagbank-connect')?>:</label>
                                <input type="tel" id="box-empty_weight" name="box-empty_weight" placeholder="g" required title="<?php _e('Peso da embalagem vazia em gramas.', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Max Weight</span></legend>
                                <label for="box-max_weight"><?php _e('Peso máximo', 'pagbank-connect')?>:</label>
                                <input type="tel" id="box-max_weight" name="box-max_weight" placeholder="g" required title="<?php _e('Peso máximo suportado pela embalagem, em gramas.', 'pagbank-connect')?>">
                            </fieldset>
                            <fieldset>
                                <legend class="screen-reader-text"><span>Active</span></legend>
                                <label for="box-active"><?php _e('Disponível', 'pagbank-connect')?>:</label>
                                <input type="checkbox" id="box-active" name="box-active">
                            </fieldset>
                        </div>
                    </form>
                </article>
                <footer>
                    <div class="inner">
                        <button id="btn-ok" class="button button-primary button-large"><?php echo __('Save', 'pagbank-connect') ?></button>
                    </div>
                </footer>
            </section>
        </div>
    </div>
    <div class="wc-backbone-modal-backdrop modal-close"></div>
</script>