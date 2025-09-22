// Adds a class when FPD wrapper exists and syncs visible qty to hidden input on submit
document.addEventListener('DOMContentLoaded', function(){
  if (document.querySelector('.fpd-product-designer-wrapper')) {
    document.documentElement.classList.add('fpd-has-designer');
    document.body.classList.add('fpd-has-designer');
  }
});

document.addEventListener('click', function(e){
  var btn = e.target.closest('button.single_add_to_cart_button, .single_add_to_cart_button');
  if(!btn) return;
  var form = btn.closest('form.cart');
  if(!form) return;
  var qty = form.querySelector('input.qty');
  var hiddenQty = form.querySelector('input[name="quantity"]');
  if(qty && hiddenQty){ hiddenQty.value = qty.value || '1'; }
});
