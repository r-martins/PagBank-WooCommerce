const PaymentInstructions = ({ instructions, checkoutClass }) => (
  <p className={`instructions checkout-${checkoutClass}-instructions`} dangerouslySetInnerHTML={{ __html: instructions }} />
);

export default PaymentInstructions;
