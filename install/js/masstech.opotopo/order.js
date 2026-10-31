document.addEventListener('click', (event) => {
  if (!(event.target instanceof Element)) return;
  const block = event.target.closest('[data-opotopo-order]');
  if (!block) return;
  if (event.target.closest('[data-opotopo-add-place]')) {
    const places = block.querySelector('[data-opotopo-places]');
    const template = places.querySelector('template');
    const index = places.querySelectorAll('[data-opotopo-place]').length;
    template.insertAdjacentHTML(
      'beforebegin',
      template.innerHTML.replaceAll('__index__', String(index)).replaceAll('__number__', String(index + 1)),
    );
    return;
  }
  const button = event.target.closest('[data-opotopo-action]');
  if (!button) return;
  const data = new FormData();
  data.append('orderId', block.dataset.opotopoOrder);
  block.querySelectorAll('input[name], select[name], textarea[name]').forEach((field) => {
    if (!field.closest('template')) data.append(field.name, field.value);
  });
  const error = block.querySelector('[data-opotopo-error]');
  button.disabled = true;
  BX.ajax.runAction('masstech:opotopo.Controller.Fulfillment.' + button.dataset.opotopoAction, { data })
    .then(() => window.location.reload(), (response) => {
      button.disabled = false;
      error.textContent = response.errors.map((item) => item.message).join('\n');
      error.hidden = false;
    });
});
