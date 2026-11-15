import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { api } from '../api/client.js'
import { LoadError, Loading, Pagination } from '../components/ui.jsx'
import { useApi, useDebounced } from '../hooks/useApi.js'
import { ORDER_STATUS_LABELS, formatDateTime, formatPence } from '../utils/format.js'
import './admin.css'
import './shop.css'

const ACTION_LABELS = {
  processing: 'Mark as paid',
  completed: 'Mark as completed',
  cancelled: 'Cancel order',
}

export function OrderStatus({ status }) {
  return <span className={`badge order-${status}`}>{ORDER_STATUS_LABELS[status] ?? status}</span>
}

const toPence = (value) => {
  const n = Number(String(value).replace(/[£,\s]/g, ''))
  return value === '' || Number.isNaN(n) ? null : Math.round(n * 100)
}
const toPounds = (pence) => (pence === null || pence === undefined ? '' : (pence / 100).toFixed(2))

const EMPTY = {
  name: '',
  price: '',
  sale_price: '',
  short_description: '',
  category_ids: [],
  track_stock: false,
  stock_quantity: '',
  in_stock: true,
  recommended_after: '',
  status: 'publish',
}

export default function Shop() {
  const [params, setParams] = useSearchParams()
  const tab = params.get('tab') === 'orders' ? 'orders' : 'products'
  const select = (next) => setParams(next === 'orders' ? { tab: 'orders' } : {}, { replace: true })
  return (
    <>
      <div className="page-head">
        <div>
          <h1>Shop</h1>
          <p className="muted page-sub">Manage the products on the website shop and fulfil orders.</p>
        </div>
      </div>
      <div className="tabs" role="tablist" aria-label="Shop sections">
        {[
          ['products', 'Products'],
          ['orders', 'Orders'],
        ].map(([key, label]) => (
          <button
            key={key}
            type="button"
            role="tab"
            id={`tab-${key}`}
            aria-selected={tab === key}
            aria-controls={`panel-${key}`}
            className="tab"
            onClick={() => select(key)}
          >
            {label}
          </button>
        ))}
      </div>
      <div role="tabpanel" id={`panel-${tab}`} aria-labelledby={`tab-${tab}`}>
        {tab === 'products' ? <Products /> : <Orders />}
      </div>
    </>
  )
}

function Products() {
  const [q, setQ] = useState('')
  const search = useDebounced(q)
  const products = useApi('/shop/products', { q: search || undefined })
  const categories = useApi('/shop/categories')
  const treatments = useApi('/treatments')
  const [editing, setEditing] = useState(null) // null | 'new' | product
  const [form, setForm] = useState(EMPTY)
  const [errors, setErrors] = useState({})
  const [banner, setBanner] = useState(null)
  const [saving, setSaving] = useState(false)

  function open(product) {
    setErrors({})
    setBanner(null)
    if (product === 'new') setForm(EMPTY)
    else
      setForm({
        name: product.name,
        price: toPounds(product.price_pence),
        sale_price: toPounds(product.sale_price_pence),
        short_description: product.short_description,
        category_ids: product.category_ids,
        track_stock: product.stock_quantity !== null,
        stock_quantity: product.stock_quantity ?? '',
        in_stock: product.stock_status !== 'outofstock',
        recommended_after: product.recommended_after,
        status: product.status === 'draft' ? 'draft' : 'publish',
      })
    setEditing(product)
  }

  async function save(event) {
    event.preventDefault()
    const price = toPence(form.price)
    const sale = toPence(form.sale_price)
    const found = {}
    if (form.name.trim().length < 2) found.name = 'Enter a product name.'
    if (price === null || price < 0) found.price_pence = 'Enter a price, for example 4.99.'
    if (sale !== null && price !== null && sale >= price) found.sale_price_pence = 'The sale price must be lower than the price.'
    if (form.track_stock && (form.stock_quantity === '' || Number(form.stock_quantity) < 0))
      found.stock_quantity = 'Enter how many are in stock.'
    setErrors(found)
    if (Object.keys(found).length) return
    const body = {
      name: form.name.trim(),
      status: form.status,
      price_pence: price,
      sale_price_pence: sale,
      short_description: form.short_description,
      category_ids: form.category_ids,
      stock_quantity: form.track_stock ? Number(form.stock_quantity) : null,
      in_stock: form.in_stock,
      recommended_after: form.recommended_after,
    }
    setSaving(true)
    try {
      if (editing === 'new') await api('/shop/products', { method: 'POST', body })
      else await api(`/shop/products/${editing.id}`, { method: 'PATCH', body })
      setBanner({ type: 'success', text: `Product “${body.name}” saved.` })
      setEditing(null)
      products.reload()
    } catch (err) {
      setErrors(err.fieldErrors?.() || {})
      setBanner({ type: 'error', text: err.message })
    } finally {
      setSaving(false)
    }
  }

  async function remove(product) {
    if (!window.confirm(`Remove “${product.name}” from the shop? It can be restored from the WordPress bin.`)) return
    try {
      await api(`/shop/products/${product.id}`, { method: 'DELETE' })
      setBanner({ type: 'success', text: `“${product.name}” was removed.` })
      if (editing?.id === product.id) setEditing(null)
      products.reload()
    } catch (err) {
      setBanner({ type: 'error', text: err.message })
    }
  }

  const set = (field) => (e) =>
    setForm((f) => ({ ...f, [field]: e.target.type === 'checkbox' ? e.target.checked : e.target.value }))
  const fieldError = (key) =>
    errors[key] && (
      <div id={`p-${key}-err`} className="field-error">
        {errors[key]}
      </div>
    )
  const describedBy = (key) => (errors[key] ? `p-${key}-err` : undefined)

  return (
    <>
      <div className="toolbar">
        <div className="field toolbar-search">
          <label htmlFor="p-search">Search products</label>
          <input id="p-search" type="search" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <button type="button" className="btn" onClick={() => open('new')}>
          Add product
        </button>
      </div>
      {banner && (
        <p className={`banner banner-${banner.type}`} role={banner.type === 'error' ? 'alert' : 'status'}>
          {banner.text}
        </p>
      )}
      <LoadError error={products.error} onRetry={products.reload} />
      {products.loading && !products.data && <Loading lines={5} />}
      <div className={editing ? 'admin-split' : ''}>
        {products.data &&
          (products.data.length ? (
            <div className="card table-card">
              <table className="data responsive">
                <caption className="visually-hidden">Products</caption>
                <thead>
                  <tr>
                    <th scope="col">Product</th>
                    <th scope="col" className="num">Price</th>
                    <th scope="col">Stock</th>
                    <th scope="col">Visibility</th>
                    <th scope="col">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {products.data.map((p) => (
                    <tr key={p.id}>
                      <td data-label="Product">
                        <div className="product-cell">
                          {p.image_url ? (
                            <img src={p.image_url} alt="" className="product-thumb" loading="lazy" />
                          ) : (
                            <span className="product-thumb product-thumb-empty" aria-hidden="true">
                              {p.name.slice(0, 1)}
                            </span>
                          )}
                          <div>
                            <div className="strong">{p.name}</div>
                            {p.short_description && <div className="muted small clamp-1">{p.short_description}</div>}
                          </div>
                        </div>
                      </td>
                      <td data-label="Price" className="num">
                        {p.sale_price_pence !== null ? (
                          <>
                            <span className="strong">{formatPence(p.sale_price_pence)}</span>{' '}
                            <s className="muted small">{formatPence(p.price_pence)}</s>
                          </>
                        ) : (
                          formatPence(p.price_pence)
                        )}
                      </td>
                      <td data-label="Stock">
                        {p.stock_status === 'outofstock' ? (
                          <span className="badge order-cancelled">Out of stock</span>
                        ) : p.stock_quantity !== null ? (
                          `${p.stock_quantity} in stock`
                        ) : (
                          'In stock'
                        )}
                      </td>
                      <td data-label="Visibility">
                        {p.status === 'publish' ? (
                          <span className="badge order-completed">On the website</span>
                        ) : (
                          <span className="badge order-pending">Hidden (draft)</span>
                        )}
                      </td>
                      <td data-label="Actions">
                        <div className="row">
                          <button type="button" className="btn-link" onClick={() => open(p)}>
                            Edit<span className="visually-hidden"> {p.name}</span>
                          </button>
                          {p.status === 'publish' && (
                            <a className="btn-link" href={p.permalink} target="_blank" rel="noreferrer">
                              View<span className="visually-hidden"> {p.name} on the website (opens in a new tab)</span>
                            </a>
                          )}
                          <button type="button" className="btn-link danger" onClick={() => remove(p)}>
                            Remove<span className="visually-hidden"> {p.name}</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <div className="card empty-state">
              <h2>{search ? 'No products match your search' : 'No products yet'}</h2>
              <p className="muted">
                {search
                  ? 'Try a different name.'
                  : 'Add the aftercare products you sell. They appear in the website shop straight away.'}
              </p>
              {!search && (
                <button type="button" className="btn" onClick={() => open('new')}>
                  Add your first product
                </button>
              )}
            </div>
          ))}

        {editing && (
          <form className="card side-panel" onSubmit={save} aria-labelledby="product-form-h" noValidate>
            <h2 id="product-form-h">{editing === 'new' ? 'Add product' : `Edit ${editing.name}`}</h2>
            <div className="field">
              <label htmlFor="p-name">Name</label>
              <input id="p-name" value={form.name} onChange={set('name')} aria-invalid={Boolean(errors.name)} aria-describedby={describedBy('name')} />
              {fieldError('name')}
            </div>
            <div className="field-pair">
              <div className="field">
                <label htmlFor="p-price">Price (£)</label>
                <input
                  id="p-price"
                  inputMode="decimal"
                  value={form.price}
                  onChange={set('price')}
                  placeholder="0.00"
                  aria-invalid={Boolean(errors.price_pence)}
                  aria-describedby={describedBy('price_pence')}
                />
                {fieldError('price_pence')}
              </div>
              <div className="field">
                <label htmlFor="p-sale">Sale price (£, optional)</label>
                <input
                  id="p-sale"
                  inputMode="decimal"
                  value={form.sale_price}
                  onChange={set('sale_price')}
                  aria-invalid={Boolean(errors.sale_price_pence)}
                  aria-describedby={describedBy('sale_price_pence')}
                />
                {fieldError('sale_price_pence')}
              </div>
            </div>
            <div className="field">
              <label htmlFor="p-desc">Short description</label>
              <textarea id="p-desc" rows={3} value={form.short_description} onChange={set('short_description')} />
            </div>
            {categories.data?.length > 0 && (
              <fieldset className="field">
                <legend>Categories</legend>
                <div className="check-list">
                  {categories.data.map((c) => (
                    <label key={c.id}>
                      <input
                        type="checkbox"
                        checked={form.category_ids.includes(c.id)}
                        onChange={(e) =>
                          setForm((f) => ({
                            ...f,
                            category_ids: e.target.checked
                              ? [...f.category_ids, c.id]
                              : f.category_ids.filter((x) => x !== c.id),
                          }))
                        }
                      />{' '}
                      {c.name}
                    </label>
                  ))}
                </div>
              </fieldset>
            )}
            <fieldset className="field">
              <legend>Stock</legend>
              <label className="check">
                <input type="checkbox" checked={form.track_stock} onChange={set('track_stock')} /> Track stock quantity
              </label>
              {form.track_stock ? (
                <div className="field">
                  <label htmlFor="p-qty">Quantity in stock</label>
                  <input
                    id="p-qty"
                    type="number"
                    min="0"
                    value={form.stock_quantity}
                    onChange={set('stock_quantity')}
                    aria-invalid={Boolean(errors.stock_quantity)}
                    aria-describedby={describedBy('stock_quantity')}
                  />
                  {fieldError('stock_quantity')}
                </div>
              ) : (
                <label className="check">
                  <input type="checkbox" checked={form.in_stock} onChange={set('in_stock')} /> In stock
                </label>
              )}
            </fieldset>
            <div className="field">
              <label htmlFor="p-after">Recommended after treatment</label>
              <select id="p-after" value={form.recommended_after} onChange={set('recommended_after')}>
                <option value="">— none —</option>
                {(treatments.data || []).map((t) => (
                  <option key={t.id} value={t.slug}>
                    {t.name}
                  </option>
                ))}
              </select>
            </div>
            <div className="field">
              <label htmlFor="p-status">Visibility</label>
              <select id="p-status" value={form.status} onChange={set('status')}>
                <option value="publish">On the website</option>
                <option value="draft">Hidden (draft)</option>
              </select>
            </div>
            {editing !== 'new' && (
              <p className="small">
                <a href={editing.edit_url} target="_blank" rel="noreferrer">
                  Add photos and a full description in WordPress (opens in a new tab)
                </a>
              </p>
            )}
            <div className="row">
              <button type="submit" className="btn" disabled={saving}>
                {saving ? 'Saving…' : 'Save product'}
              </button>
              <button type="button" className="btn btn-secondary" onClick={() => setEditing(null)}>
                Cancel
              </button>
            </div>
          </form>
        )}
      </div>
    </>
  )
}

function Orders() {
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const search = useDebounced(q)
  const orders = useApi('/shop/orders', { status: status || undefined, q: search || undefined, page })
  const [selected, setSelected] = useState(null)
  const [banner, setBanner] = useState(null)

  async function change(order, next) {
    if (next === 'cancelled' && !window.confirm(`Cancel order #${order.number}?`)) return
    try {
      const updated = await api(`/shop/orders/${order.id}`, { method: 'PATCH', body: { status: next } })
      setBanner({ type: 'success', text: `Order #${order.number} is now “${ORDER_STATUS_LABELS[next]}”.` })
      if (selected?.id === order.id) setSelected(updated)
      orders.reload()
    } catch (err) {
      setBanner({ type: 'error', text: err.message })
    }
  }

  return (
    <>
      <div className="toolbar">
        <div className="field">
          <label htmlFor="o-status">Status</label>
          <select
            id="o-status"
            value={status}
            onChange={(e) => {
              setStatus(e.target.value)
              setPage(1)
            }}
          >
            <option value="">All orders</option>
            <option value="on-hold">Awaiting payment</option>
            <option value="processing">Paid – to dispatch</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div className="field toolbar-search">
          <label htmlFor="o-search">Search (name, email or order number)</label>
          <input
            id="o-search"
            type="search"
            value={q}
            onChange={(e) => {
              setQ(e.target.value)
              setPage(1)
            }}
          />
        </div>
      </div>
      {banner && (
        <p className={`banner banner-${banner.type}`} role={banner.type === 'error' ? 'alert' : 'status'}>
          {banner.text}
        </p>
      )}
      <LoadError error={orders.error} onRetry={orders.reload} />
      {orders.loading && !orders.data && <Loading lines={5} />}
      <div className={selected ? 'admin-split' : ''}>
        {orders.data &&
          (orders.data.items.length ? (
            <div>
              <div className="card table-card">
                <table className="data responsive">
                  <caption className="visually-hidden">Orders</caption>
                  <thead>
                    <tr>
                      <th scope="col">Order</th>
                      <th scope="col">Customer</th>
                      <th scope="col">Delivery</th>
                      <th scope="col" className="num">Total</th>
                      <th scope="col">Status</th>
                      <th scope="col">Next step</th>
                    </tr>
                  </thead>
                  <tbody>
                    {orders.data.items.map((o) => (
                      <tr key={o.id} className={selected?.id === o.id ? 'is-selected' : undefined}>
                        <td data-label="Order">
                          <button type="button" className="btn-link strong" onClick={() => setSelected(o)}>
                            #{o.number}
                            <span className="visually-hidden"> details</span>
                          </button>
                          <div className="muted small">{formatDateTime(o.created_at)}</div>
                        </td>
                        <td data-label="Customer">
                          {o.customer_name || '—'}
                          <div className="muted small">{o.customer_email}</div>
                        </td>
                        <td data-label="Delivery">
                          {o.delivery_method || '—'}
                          {o.collection_clinic && <div className="muted small">{o.collection_clinic}</div>}
                        </td>
                        <td data-label="Total" className="num">
                          {formatPence(o.total_pence)}
                        </td>
                        <td data-label="Status">
                          <OrderStatus status={o.status} />
                        </td>
                        <td data-label="Next step">
                          <div className="row">
                            {o.allowed_statuses
                              .filter((s) => s !== 'cancelled')
                              .map((s) => (
                                <button key={s} type="button" className="btn btn-small" onClick={() => change(o, s)}>
                                  {ACTION_LABELS[s]}
                                  <span className="visually-hidden"> #{o.number}</span>
                                </button>
                              ))}
                            {!o.allowed_statuses.length && <span className="muted">—</span>}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <Pagination
                page={orders.data.page}
                pages={orders.data.pages}
                total={orders.data.total}
                pageSize={20}
                onChange={setPage}
              />
            </div>
          ) : (
            <div className="card empty-state">
              <h2>No orders {status || search ? 'match these filters' : 'yet'}</h2>
              <p className="muted">Orders placed on the website shop appear here.</p>
            </div>
          ))}

        {selected && (
          <section className="card side-panel" aria-labelledby="order-h">
            <div className="panel-head">
              <h2 id="order-h">Order #{selected.number}</h2>
              <button type="button" className="btn-link" onClick={() => setSelected(null)}>
                Close
              </button>
            </div>
            <p>
              <OrderStatus status={selected.status} />
            </p>
            <dl className="details">
              <dt>Placed</dt>
              <dd>{formatDateTime(selected.created_at)}</dd>
              <dt>Customer</dt>
              <dd>
                {selected.customer_name}
                <br />
                <a href={`mailto:${selected.customer_email}`}>{selected.customer_email}</a>
                {selected.customer_phone && (
                  <>
                    <br />
                    <a href={`tel:${selected.customer_phone.replace(/\s+/g, '')}`}>{selected.customer_phone}</a>
                  </>
                )}
              </dd>
              <dt>Delivery</dt>
              <dd>
                {selected.delivery_method || '—'}
                {selected.collection_clinic ? (
                  <div>Collect from {selected.collection_clinic}</div>
                ) : (
                  selected.delivery_address && <div>{selected.delivery_address}</div>
                )}
              </dd>
              <dt>Payment</dt>
              <dd>{selected.payment_method || '—'}</dd>
              {selected.customer_note && (
                <>
                  <dt>Customer note</dt>
                  <dd>{selected.customer_note}</dd>
                </>
              )}
            </dl>
            <table className="data">
              <caption className="visually-hidden">Items</caption>
              <thead>
                <tr>
                  <th scope="col">Item</th>
                  <th scope="col" className="num">Qty</th>
                  <th scope="col" className="num">Total</th>
                </tr>
              </thead>
              <tbody>
                {selected.items.map((item, i) => (
                  <tr key={i}>
                    <td>{item.name}</td>
                    <td className="num">{item.quantity}</td>
                    <td className="num">{formatPence(item.total_pence)}</td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr>
                  <th scope="row" colSpan={2}>
                    Order total (incl. delivery)
                  </th>
                  <td className="num strong">{formatPence(selected.total_pence)}</td>
                </tr>
              </tfoot>
            </table>
            <div className="row" style={{ marginTop: 16 }}>
              {selected.allowed_statuses.map((s) => (
                <button
                  key={s}
                  type="button"
                  className={`btn ${s === 'cancelled' ? 'btn-danger' : ''}`}
                  onClick={() => change(selected, s)}
                >
                  {ACTION_LABELS[s]}
                </button>
              ))}
            </div>
          </section>
        )}
      </div>
    </>
  )
}
