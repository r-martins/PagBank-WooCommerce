const TermsAndConditions = ({ description, checkoutClass='pro' }) => (
  <div className={`checkout-${checkoutClass}-terms-and-conditions`}>
      {description}
  </div>
);

export default TermsAndConditions;
