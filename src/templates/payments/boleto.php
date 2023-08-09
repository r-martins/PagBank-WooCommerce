<?php
/** @var \RM_PagBank\Connect\Gateway $this */

use RM_PagBank\Connect;

$expiry = (int)$this->get_option('boleto_expiry_days');
?>
<p class="instructions">
    <?php echo $this->get_option('boleto_instructions'); ?>
    <br/>
    <?php echo sprintf( _n( 'Seu boleto vencerá amanhã.', 'Seu boleto vence em %d dias.', $expiry, Connect::DOMAIN ), $expiry ); ?>
</p>