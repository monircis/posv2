// The printed invoice / receipt for a sale, laid out from Settings > Invoice & receipt
// (paper size, logo, ABN, payment terms, footer) and Settings > Business (name, address, contact).
// `sale` is what POST /api/sales and GET /api/sales/{id} return.

function money(n) {
  return (Number(n) || 0).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function esc(v) {
  return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

// Line breaks in the address and footer stay as line breaks.
function lines(v) {
  return esc(v).replace(/\n/g, '<br>');
}

function dateTime(iso) {
  const d = new Date(iso);
  const p = n => String(n).padStart(2, '0');
  return p(d.getDate()) + '/' + p(d.getMonth() + 1) + '/' + d.getFullYear() + ' ' + p(d.getHours()) + ':' + p(d.getMinutes());
}

function qtyText(item) {
  const q = Number(item.qty);
  if (item.unit === 'kg') return (Math.round(q * 1000) / 1000) + ' kg';
  return q + ' ×';
}

export function invoiceHtml(sale) {
  const inv = sale.invoice || {};
  const biz = sale.business || {};
  const roll = inv.paper !== 'A4';
  const number = (inv.receipt_prefix || '') + sale.ref;

  // Money rows under the items, the same as on the POS receipt.
  const rows = [
    ['Items', money(sale.subtotal)],
    sale.group_discount ? ['Group discount ' + sale.group_percent + '%', '−' + money(sale.group_discount)] : null,
    sale.discount ? [sale.discount_type === 'amount' ? 'Discount' : 'Discount ' + sale.discount_percent + '%', '−' + money(sale.discount)] : null,
    sale.coins_used ? ['Coins used ' + sale.coins_used, '−' + money(sale.coin_discount)] : null,
    sale.delivery_fee ? ['Delivery', money(sale.delivery_fee)] : null,
    sale.card_surcharge ? ['Card surcharge', money(sale.card_surcharge)] : null,
    sale.rounding ? ['Rounding', (sale.rounding > 0 ? '+' : '−') + money(Math.abs(sale.rounding))] : null,
  ].filter(Boolean);

  const paid = (sale.payments || []).map(p => [p.method === 'cash' ? 'Cash' : 'Card', money(p.amount)]);
  if (sale.tendered) paid.push(['Cash handed over', money(sale.tendered)]);
  if (sale.change_given) paid.push(['Change', money(sale.change_given)]);
  if (sale.due) paid.push(['Still owing', money(sale.due)]);
  if (sale.tip) paid.push(['Tip for ' + (sale.tip.employee ? sale.tip.employee.first : 'staff') + ' (not in total)', money(sale.tip.amount)]);

  const row = r => '<tr><td>' + esc(r[0]) + '</td><td class="r">' + esc(r[1]) + '</td></tr>';

  const items = (sale.items || []).map(x => '<tr class="item">'
    + '<td>' + esc(x.name) + (x.note ? '<div class="note">' + esc(x.note) + '</div>' : '')
    + '<div class="sub">' + esc(qtyText(x)) + ' ' + money(x.price) + (x.unit === 'kg' ? '/kg' : '') + (Number(x.discount_percent) ? ' · −' + Number(x.discount_percent) + '%' : '') + '</div></td>'
    + '<td class="r">' + money(x.total) + '</td></tr>').join('');

  const customer = sale.contact
    ? '<div><b>' + esc(sale.contact.name) + '</b></div>' + (sale.contact.address ? '<div>' + lines(sale.contact.address) + '</div>' : '') + (sale.contact.phone ? '<div>' + esc(sale.contact.phone) + '</div>' : '')
    : '<div>' + esc(sale.customer_name || 'Walk in') + '</div>';

  const terms = sale.due
    ? '<div class="terms">Payment terms: ' + esc(inv.payment_terms || 'Due on receipt') + (sale.due_date ? ' · due ' + esc(sale.due_date.split('-').reverse().join('/')) : '') + '</div>'
    : '';

  const css = roll
    ? '@page { size: 80mm auto; margin: 0 } body { width: 72mm; margin: 0 auto; padding: 4mm 0; font-size: 11.5px }'
      + ' .logo { max-width: 40mm; max-height: 18mm } .head { text-align: center } .meta, .cust { text-align: center }'
    : '@page { size: A4; margin: 15mm } body { margin: 0 auto; max-width: 180mm; font-size: 12.5px }'
      + ' .logo { max-width: 60mm; max-height: 25mm } .top { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start }'
      + ' .head { text-align: left } .meta { text-align: right } h1 { font-size: 22px }';

  return '<!doctype html><html><head><meta charset="utf-8"><title>' + esc(number) + '</title><style>'
    + '* { box-sizing: border-box } body { font-family: Arial, Helvetica, sans-serif; color: #111 } '
    + 'h1 { margin: 6px 0 2px; font-size: 16px } .muted { color: #555 } table { width: 100%; border-collapse: collapse } '
    + 'td { padding: 3px 0; vertical-align: top } .r { text-align: right; white-space: nowrap; padding-left: 8px } '
    + '.item td { border-bottom: 1px dashed #bbb; padding: 5px 0 } .sub { color: #555; font-size: 0.9em } .note { font-style: italic; color: #8a5300; font-size: 0.9em } '
    + '.total td { font-size: 1.25em; font-weight: bold; border-top: 2px solid #111; padding-top: 6px } '
    + '.box { margin: 10px 0 } .title { font-weight: bold; letter-spacing: .08em; text-transform: uppercase; font-size: 0.95em; margin: 10px 0 4px } '
    + '.foot { margin-top: 14px; text-align: center; color: #333; white-space: normal } .terms { margin-top: 8px; font-weight: bold } '
    + css + '</style></head><body>'
    + '<div class="top"><div class="head">'
    + (inv.show_logo && biz.logo_url ? '<img class="logo" src="' + esc(biz.logo_url) + '" alt="">' : '')
    + '<h1>' + esc(biz.business_name) + '</h1>'
    + (biz.legal_name && biz.legal_name !== biz.business_name ? '<div class="muted">' + esc(biz.legal_name) + '</div>' : '')
    + (inv.show_abn && biz.abn ? '<div>ABN ' + esc(biz.abn) + '</div>' : '')
    + (biz.address ? '<div class="muted">' + lines(biz.address) + '</div>' : '')
    + (biz.phone || biz.email ? '<div class="muted">' + esc([biz.phone, biz.email].filter(Boolean).join(' · ')) + '</div>' : '')
    + '</div><div class="meta">'
    + '<div class="title">Tax invoice</div>'
    + '<div><b>' + esc(number) + '</b></div>'
    + '<div>' + esc(dateTime(sale.sold_at)) + '</div>'
    + '<div>' + esc((sale.branch ? sale.branch.name : '') + ' · ' + sale.order_type + (sale.table_no ? ' · Table ' + sale.table_no : '')) + '</div>'
    + '<div class="muted">Served by ' + esc(sale.created_by || '') + '</div>'
    + '</div></div>'
    + '<div class="box cust"><div class="title">Customer</div>' + customer + '</div>'
    + '<table>' + items + '</table>'
    + '<table class="box">' + rows.map(row).join('')
    + '<tr class="total"><td>Total</td><td class="r">$' + money(sale.total) + '</td></tr>'
    + '<tr><td class="muted">GST included</td><td class="r muted">' + money(sale.gst) + '</td></tr></table>'
    + '<table class="box">' + paid.map(row).join('') + '</table>'
    + terms
    + (sale.status === 'cancelled' ? '<div class="terms">CANCELLED</div>' : '')
    + (inv.footer_text ? '<div class="foot">' + lines(inv.footer_text) + '</div>' : '')
    + '</body></html>';
}

// Prints the invoice from a hidden frame the size of the paper, so the page itself is not printed.
export function printInvoice(sale) {
  const roll = !sale.invoice || sale.invoice.paper !== 'A4';
  const frame = document.createElement('iframe');
  frame.setAttribute('style', 'position:fixed;left:-10000px;top:0;border:0;' + (roll ? 'width:80mm;height:200mm;' : 'width:210mm;height:297mm;'));
  document.body.appendChild(frame);
  const doc = frame.contentWindow.document;
  doc.open();
  doc.write(invoiceHtml(sale));
  doc.close();
  const remove = () => { if (frame.parentNode) frame.parentNode.removeChild(frame); };
  frame.contentWindow.onafterprint = remove;
  // Give the logo a moment to load before printing.
  setTimeout(() => { frame.contentWindow.focus(); frame.contentWindow.print(); }, 400);
  setTimeout(remove, 120000);
}
