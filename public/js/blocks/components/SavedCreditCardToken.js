import React, { useState } from "react";
import { __ } from "@wordpress/i18n";

const SavedCreditCardToken = () => {
  const [isChecked, setIsChecked] = useState(false);
  const handleCheckboxChange = () => {
    setIsChecked(!isChecked);
  };

  return (
    <div className="form-row form-row-wide woocommerce-SavedPaymentMethods-saveNew" style={{ marginTop: '1em' }}>
      <label htmlFor="rm-pagbank-cc-new-payment-method-in-block" className="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
        <input
          type="checkbox"
          id="rm-pagbank-cc-new-payment-method-in-block"
          name="rm-pagbank-cc-new-payment-method-in-block"
          className="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
          checked={isChecked}
          onChange={handleCheckboxChange}
        />
        <span className="woocommerce-form__label-text">
          {__("Salvar cartão para futuras compras", "pagbank-connect")}
        </span>
      </label>
    </div>
  );
};

export default SavedCreditCardToken;
