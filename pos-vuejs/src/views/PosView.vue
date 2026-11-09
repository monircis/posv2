<template>
  <div data-screen-label="POS Terminal" style="height:100%;min-height:720px;min-width:1120px;display:flex;flex-direction:column;background:#eef2f6;position:relative;overflow:hidden;font-family:'Public Sans',system-ui,sans-serif;color:#0f1c2b">
    <div style="height:58px;flex:none;background:#0f1c2b;display:flex;align-items:center;gap:14px;padding:0 16px">
      <template v-if="vm.hasExit">
        <div @click="vm.exit" title="Back to Sell" style="height:34px;padding:0 12px;border-radius:8px;border:1px solid rgba(255,255,255,.18);display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#c4dced;cursor:pointer;flex:none" v-pseudo:hover="'background:rgba(255,255,255,.06)'">‹ Sell</div>
      </template>
      <div style="width:32px;height:32px;border-radius:9px;background:var(--c-accent);color:#06283a;display:grid;place-items:center;font-weight:700;font-size:14px;flex:none">M</div>
      <div style="min-width:0">
        <div style="font-size:13.5px;font-weight:600;color:#ffffff;line-height:1.2">Madinah Butchery</div>
        <div style="font-size:11px;color:#8fa3b6">Register 1 · <select v-if="vm.canPickBranch" :value="vm.branchId" @change="vm.onBranch" title="Branch" style="background:transparent;border:none;color:#c4dced;font-family:inherit;font-size:11px;font-weight:600;cursor:pointer;outline:none;padding:0"><option v-for="b in vm.branches" :key="b.id" :value="b.id" style="color:#0f1c2b">{{ b.name }}</option></select><template v-else>{{ vm.branchName }}</template></div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;background:rgba(47,158,99,.14);border:1px solid rgba(47,158,99,.38);border-radius:99px;padding:5px 12px;margin-left:6px">
        <div style="width:7px;height:7px;border-radius:50%;background:#2f9e63"></div>
        <div style="font-size:11.5px;font-weight:600;color:#86dcaa">Today</div>
        <div style="font-family:'IBM Plex Mono',monospace;font-size:11.5px;color:#cfe9da">{{ vm.sessLine }}</div>
      </div>
      <div style="margin-left:auto;display:flex;align-items:center;gap:6px">
        <CashRegister :key="userKey" :branch-id="vm.branchId" :required="vm.requireRegister" :ready="vm.catalogReady" :sale-ref="vm.doneSale ? vm.doneSale.ref : ''" />
        <div @click="vm.openHeld" style="height:34px;padding:0 12px;border-radius:9px;background:rgba(255,255,255,.08);color:#dbe5ee;display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:rgba(255,255,255,.16)'">Held orders<span :style="vm.heldBadgeStyle">{{ vm.heldCount }}</span></div>
        <div @click="openCustomerDisplay" title="Open the screen for the customer (drag it to the second monitor)" style="height:34px;padding:0 12px;border-radius:9px;background:rgba(255,255,255,.08);color:#dbe5ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:rgba(255,255,255,.16)'">Customer display</div>
        <div @click="vm.openRecent" style="height:34px;padding:0 12px;border-radius:9px;background:rgba(255,255,255,.08);color:#dbe5ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:rgba(255,255,255,.16)'">Recent sales</div>
        <div style="width:1px;height:24px;background:rgba(255,255,255,.14);margin:0 6px"></div>
        <div style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;color:#ffffff">{{ vm.clock }}</div>
        <div style="position:relative;margin-left:4px">
          <div @click="userMenu = !userMenu" title="Sign out or switch user" style="display:flex;align-items:center;gap:8px;padding:3px 10px 3px 3px;border-radius:9px;background:rgba(255,255,255,.06);cursor:pointer" v-pseudo:hover="'background:rgba(255,255,255,.14)'">
            <div style="width:28px;height:28px;border-radius:7px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-size:11px;font-weight:600">{{ vm.userInitials }}</div>
            <div style="font-size:12.5px;font-weight:500;color:#dbe5ee">{{ vm.userName }}</div>
            <div style="font-size:10px;color:#8fa3b6">▾</div>
          </div>
          <template v-if="userMenu">
            <div @click="userMenu = false" style="position:fixed;inset:0;z-index:44"></div>
            <div style="position:absolute;right:0;top:42px;z-index:45;width:230px;background:#ffffff;border-radius:12px;box-shadow:0 18px 40px -12px rgba(9,22,35,.45);padding:6px;color:#0f1c2b">
              <div style="padding:8px 10px 10px;border-bottom:1px solid #eef2f6;margin-bottom:4px">
                <div style="font-size:13px;font-weight:600">{{ vm.userName }}</div>
                <div style="font-size:11.5px;color:#7d8b9c">The order on screen is kept for the next person.</div>
              </div>
              <div @click="signOut" style="padding:9px 10px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Sign out / switch user</div>
            </div>
          </template>
        </div>
      </div>
    </div>
    <div style="flex:1;min-height:0;display:grid;grid-template-columns:108px minmax(0,1fr) 420px">
      <div style="background:#ffffff;border-right:1px solid #dfe6ee;overflow-y:auto;padding:10px 8px;display:flex;flex-direction:column;gap:6px">
        <template v-for="(c, $index) in vm.cats" :key="$index">
          <div @click="c.onClick" :style="c.style" v-pseudo:hover="'box-shadow:inset 0 0 0 1px #c9d4e0'">
            <div :style="c.codeStyle">{{ c.code }}</div>
            <div style="font-size:11.5px;font-weight:600;line-height:1.2;text-align:center">{{ c.name }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;opacity:.65">{{ c.count }}</div>
          </div>
        </template>
      </div>
      <div style="display:flex;flex-direction:column;min-width:0;min-height:0;position:relative">
        <div style="flex:none;padding:14px 16px 10px">
          <div style="display:flex;gap:8px;align-items:center">
            <div style="flex:1;min-width:0">
          <div style="position:relative"><div style="position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#7d8b9c;font-size:15px">⚲</div> <input :ref="(el) => { if (vm.searchRef) vm.searchRef.current = el }" :value="vm.q" @input="vm.onSearch" @keydown="vm.onSearchKey" placeholder="Scan a barcode, or search by name or SKU" style="width:100%;height:50px;border:1.5px solid var(--c-primary);border-radius:12px;background:#ffffff;padding:0 96px 0 42px;font-family:'Public Sans',sans-serif;font-size:15px;color:#0f1c2b;outline:none;box-shadow:0 0 0 4px rgba(22,104,201,.08)" /> <template v-if="vm.hasQuery">
            <div @click="vm.clearSearch" style="position:absolute;right:52px;top:50%;transform:translateY(-50%);width:28px;height:28px;border-radius:7px;display:grid;place-items:center;color:#7d8b9c;cursor:pointer;font-size:15px" v-pseudo:hover="'background:#eef2f6;color:#0f1c2b'">×</div>
          </template> <div style="position:absolute;right:12px;top:50%;transform:translateY(-50%);font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#5d6b7d;background:var(--c-primary-soft);border-radius:5px;padding:3px 7px">F2</div></div>
            </div>
            <div v-if="vm.searchKeypad" @click="toggleKb" title="On-screen keypad" :style="kbBtnStyle">⌨</div>
          </div>
          <div v-if="vm.searchKeypad && kbOpen" style="margin-top:10px;background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:8px;display:grid;gap:6px;user-select:none">
            <div v-for="(row, r) in KB_ROWS" :key="r" style="display:grid;grid-template-columns:repeat(10,minmax(0,1fr));gap:6px">
              <div v-for="k in row" :key="k" @mousedown.prevent @click="press(k)" :style="keyStyle(k)" v-pseudo:active="'background:#e2edfb'">{{ k === 'BACK' ? '⌫' : k }}</div>
            </div>
            <div style="display:grid;grid-template-columns:1.2fr 4fr 1.2fr 1.6fr;gap:6px">
              <div @mousedown.prevent @click="toggleKb" :style="keyStyle('Hide')">Hide</div>
              <div @mousedown.prevent @click="press(' ')" :style="keyStyle('Space')">Space</div>
              <div @mousedown.prevent @click="press('CLEAR')" :style="keyStyle('Clear')">Clear</div>
              <div @mousedown.prevent @click="press('ENTER')" :style="keyStyle('ENTER')">Enter</div>
            </div>
          </div>
        </div>
        <div style="flex:none;padding:0 18px 10px;display:flex;align-items:baseline;gap:10px">
          <div style="font-size:14px;font-weight:700;letter-spacing:-0.01em">{{ vm.gridTitle }}</div>
          <div style="font-size:12px;color:#7d8b9c">{{ vm.gridMeta }}</div>
          <label v-if="view === 'grid'" style="margin-left:auto;display:flex;align-items:center;gap:6px;font-size:12px;color:#5d6b7d;align-self:center">
            Per row
            <select :value="cols" @change="setCols(($event.target as HTMLSelectElement).value)" title="Products in one row" style="height:30px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 6px;font-family:inherit;font-size:12px;font-weight:600;color:#0f1c2b;cursor:pointer">
              <option value="auto">Auto</option>
              <option v-for="n in [3, 4, 5, 6, 7]" :key="n" :value="String(n)">{{ n }}</option>
            </select>
          </label>
          <div :style="'display:flex;border:1px solid #dfe6ee;border-radius:9px;overflow:hidden;background:#ffffff;align-self:center;' + (view === 'grid' ? '' : 'margin-left:auto')">
            <div @click="setView('grid')" title="Grid view" :style="viewBtn('grid')">▦ Grid</div>
            <div @click="setView('list')" title="List view" :style="viewBtn('list')">☰ List</div>
          </div>
        </div>
        <div style="flex:1;overflow-y:auto;min-height:0;padding:0 16px 24px">
          <div v-if="vm.catalogNote" style="border:1px dashed #cfdae6;border-radius:14px;padding:44px 20px;text-align:center;background:#ffffff;font-size:13px;color:#5d6b7d">
            {{ vm.catalogNote }}
            <div v-if="vm.catalogFailed" @click="vm.retryCatalog" style="display:flex;width:max-content;margin:14px auto 0;height:34px;padding:0 14px;border-radius:9px;border:1px solid #dfe6ee;align-items:center;font-size:12.5px;font-weight:600;color:#0f1c2b;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Try again</div>
          </div>
          <template v-if="vm.noResults">
            <div style="border:1px dashed #cfdae6;border-radius:14px;padding:44px 20px;text-align:center;background:#ffffff">
              <div style="font-size:14px;font-weight:600;margin-bottom:5px">Nothing matches “{{ vm.q }}”</div>
              <div style="font-size:12.5px;color:#7d8b9c;margin-bottom:14px">Try part of the name, or the SKU printed on the label.</div>
              <div @click="vm.clearSearch" style="display:inline-flex;height:34px;padding:0 14px;border-radius:9px;border:1px solid #dfe6ee;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Clear search</div>
            </div>
          </template>
          <div v-if="view === 'list'" style="display:grid;gap:6px">
            <template v-for="(p, $index) in vm.products" :key="$index">
              <div @click="p.onClick" :style="listRow(p)" v-pseudo:hover="'border-color:var(--c-primary)'">
                <div style="flex:1;min-width:0">
                  <div style="font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ p.name }}</div>
                  <div style="display:flex;gap:8px;align-items:center;margin-top:2px;min-width:0">
                    <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#8b98a8;white-space:nowrap">{{ p.tag }}</div>
                    <div v-if="p.rule" :style="p.ruleStyle + ';margin-top:0'">{{ p.rule }}</div>
                  </div>
                </div>
                <div :style="p.stockStyle">{{ p.stock }}</div>
                <div :style="p.inCartStyle + ';margin-left:0'">{{ p.inCart }}</div>
                <div style="width:110px;text-align:right;flex:none">
                  <span style="font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600;letter-spacing:-0.02em">{{ p.price }}</span>
                  <span style="font-size:11px;color:#8b98a8;margin-left:4px">{{ p.per }}</span>
                </div>
              </div>
            </template>
          </div>
          <div v-else :style="cols === 'auto' ? vm.gridStyle : 'display:grid;gap:10px;grid-template-columns:repeat(' + cols + ',minmax(0,1fr))'">
            <template v-for="(p, $index) in vm.products" :key="$index">
              <div @click="p.onClick" :style="p.style" v-pseudo:hover="'border-color:var(--c-primary);box-shadow:0 8px 18px -10px rgba(22,104,201,.45)'">
                <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;min-height:22px">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ p.tag }}</div>
                  <div :style="p.inCartStyle">{{ p.inCart }}</div>
                </div>
                <div :style="p.nameStyle">{{ p.name }}</div>
                <div style="display:flex;align-items:baseline;gap:5px;margin-top:10px">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:16px;font-weight:600;letter-spacing:-0.02em">{{ p.price }}</div>
                  <div style="font-size:11px;color:#8b98a8">{{ p.per }}</div>
                  <div :style="p.stockStyle">{{ p.stock }}</div>
                </div>
                <div :style="p.ruleStyle">{{ p.rule }}</div>
              </div>
            </template>
          </div>
        </div>
        <div style="position:absolute;left:50%;bottom:18px;transform:translateX(-50%);display:flex;flex-direction:column;align-items:center;gap:6px;z-index:25;pointer-events:none">
          <template v-for="(t, $index) in vm.toasts" :key="$index">
            <div style="background:#0f1c2b;color:#ffffff;border-radius:10px;padding:10px 15px;font-size:13px;display:flex;align-items:center;gap:10px;box-shadow:0 14px 30px -12px rgba(9,22,35,.6);white-space:nowrap"><div :style="t.dot"></div>{{ t.msg }}</div>
          </template>
        </div>
      </div>
      <div style="background:#ffffff;border-left:1px solid #dfe6ee;display:flex;flex-direction:column;min-height:0">
        <div style="flex:none;padding:14px 16px 12px;border-bottom:1px solid #eaeff5;display:grid;gap:10px">
          <div style="display:flex;align-items:baseline;gap:9px">
            <div style="font-size:15px;font-weight:700;letter-spacing:-0.01em">Order</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600;color:var(--c-primary)">#{{ vm.orderNo }}</div>
            <div style="margin-left:auto;font-size:11.5px;color:#7d8b9c">{{ vm.orderMeta }}</div>
          </div>
          <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:10px">
            <template v-for="(t, $index) in vm.types" :key="$index">
              <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
            </template>
          </div>
          <div style="display:grid;grid-template-columns:0.85fr 1.15fr;gap:8px">
            <div @click="vm.ctx.onClick" :style="vm.ctx.style">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:.07em;text-transform:uppercase;opacity:.7">{{ vm.ctx.kicker }}</div>
              <div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px">{{ vm.ctx.label }}</div>
              <div style="font-size:11px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ vm.ctx.sub }}</div>
            </div>
            <div @click="vm.openCust" :style="vm.custChip.style" v-pseudo:hover="'border-color:var(--c-primary)'">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:.07em;text-transform:uppercase;opacity:.7">Customer</div>
              <div style="font-size:13px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px">{{ vm.custChip.name }}</div>
              <div style="font-size:11px;opacity:.8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ vm.custChip.sub }}</div>
            </div>
          </div>
        </div>
        <div style="flex:1;overflow-y:auto;min-height:0">
          <template v-if="vm.cartEmpty">
            <div style="padding:52px 28px;text-align:center">
              <div style="width:44px;height:44px;border-radius:12px;background:#eef2f6;margin:0 auto 14px"></div>
              <div style="font-size:14px;font-weight:600;margin-bottom:5px">No items yet</div>
              <div style="font-size:12.5px;color:#7d8b9c;line-height:1.55;margin-bottom:16px;text-wrap:pretty">Scan a barcode, search, or tap a product. Cuts sold by weight ask for the weight.</div>
              <div style="display:flex;justify-content:center;gap:6px;flex-wrap:wrap">
                <div style="font-size:11px;color:#5d6b7d;background:#f2f6fa;border-radius:6px;padding:4px 8px"><span style="font-family:'IBM Plex Mono',monospace;font-weight:600">F2</span> search</div>
                <div style="font-size:11px;color:#5d6b7d;background:#f2f6fa;border-radius:6px;padding:4px 8px"><span style="font-family:'IBM Plex Mono',monospace;font-weight:600">F4</span> hold</div>
                <div style="font-size:11px;color:#5d6b7d;background:#f2f6fa;border-radius:6px;padding:4px 8px"><span style="font-family:'IBM Plex Mono',monospace;font-weight:600">F9</span> pay</div>
                <div style="font-size:11px;color:#5d6b7d;background:#f2f6fa;border-radius:6px;padding:4px 8px"><span style="font-family:'IBM Plex Mono',monospace;font-weight:600">Esc</span> close</div>
              </div>
            </div>
          </template>
          <template v-for="(l, $index) in vm.lines" :key="$index">
            <div :style="l.rowStyle">
              <div style="display:flex;align-items:center;gap:10px">
                <div @click="l.select" style="flex:1;min-width:0;cursor:pointer">
                  <div style="display:flex;align-items:center;gap:6px;min-width:0">
                    <div style="font-size:13.5px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ l.name }}</div>
                    <div :style="l.kitchenStyle">Kitchen</div>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px;margin-top:2px">
                    <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#8b98a8">{{ l.meta }}</div>
                    <div :style="l.discTagStyle">{{ l.discTag }}</div>
                  </div>
                  <div :style="l.noteStyle">{{ l.note }}</div>
                </div>
                <div style="display:flex;align-items:center;border:1px solid #dfe6ee;border-radius:9px;overflow:hidden;height:36px;flex:none;background:#ffffff">
                  <div @click="l.dec" style="width:32px;height:100%;display:grid;place-items:center;font-size:16px;color:#5d6b7d;cursor:pointer;user-select:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">−</div>
                  <div @click="l.edit" style="min-width:60px;padding:0 6px;height:100%;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;border-left:1px solid #eaeff5;border-right:1px solid #eaeff5;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-tint);color:var(--c-primary)'">{{ l.qty }}</div>
                  <div @click="l.inc" style="width:32px;height:100%;display:grid;place-items:center;font-size:16px;color:#5d6b7d;cursor:pointer;user-select:none" v-pseudo:hover="'background:#f2f6fa;color:var(--c-primary)'">+</div>
                </div>
                <div style="width:74px;text-align:right;flex:none">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:13.5px;font-weight:600">{{ l.total }}</div>
                  <div :style="l.origStyle">{{ l.orig }}</div>
                </div>
              </div>
              <template v-if="l.selected">
                <div style="margin-top:10px;display:grid;gap:8px">
                  <div style="display:flex;align-items:center;gap:6px">
                    <div style="font-size:11.5px;color:#5d6b7d;width:60px;flex:none">Discount</div>
                    <template v-for="(d, $index) in l.discChips" :key="$index">
                      <div @click="d.onClick" :style="d.style">{{ d.label }}</div>
                    </template>
                    <div @click="l.remove" style="margin-left:auto;height:30px;padding:0 11px;border-radius:8px;border:1px solid #f3cfcf;background:#fdf4f4;color:#b02020;display:flex;align-items:center;font-size:12px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">Remove</div>
                  </div>
                  <div style="display:flex;align-items:center;gap:6px"><div style="font-size:11.5px;color:#5d6b7d;width:60px;flex:none">Note</div> <input :value="l.note" @input="l.onNote" placeholder="e.g. slice thin, no onion" style="flex:1;min-width:0;height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 10px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                </div>
              </template>
            </div>
          </template>
        </div>
        <div style="flex:none;border-top:1px solid #dfe6ee;padding:12px 16px 8px;background:#fbfcfe;display:grid;gap:6px">
          <div style="display:flex;align-items:baseline;gap:10px;font-size:12.5px">
            <div style="flex:1;color:#5d6b7d">Items <span style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">({{ vm.itemCount }})</span></div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ vm.itemsTotal }}</div>
          </div>
          <template v-if="vm.showGroup">
            <div style="display:flex;align-items:baseline;gap:10px;font-size:12.5px">
              <div style="flex:1;color:#5d6b7d">{{ vm.groupLabel }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;color:#b02020">−{{ vm.groupAmt }}</div>
            </div>
          </template>
          <div @click="vm.openDisc" style="display:flex;align-items:baseline;gap:10px;font-size:12.5px;cursor:pointer">
            <div style="flex:1;color:#5d6b7d">{{ vm.discLabel }}</div>
            <div :style="vm.discAmtStyle">{{ vm.discAmt }}</div>
          </div>
          <template v-if="vm.showFee">
            <div style="display:flex;align-items:baseline;gap:10px;font-size:12.5px">
              <div style="flex:1;color:#5d6b7d">Delivery fee</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:13px">{{ vm.feeAmt }}</div>
            </div>
          </template>
          <div style="display:flex;align-items:baseline;gap:10px;font-size:12px">
            <div style="flex:1;color:#8b98a8">GST included</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:12px;color:#8b98a8">{{ vm.gst }}</div>
          </div>
          <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:#0f1c2b;border-radius:12px;margin-top:6px">
            <div style="flex:1">
              <div style="font-size:12.5px;font-weight:600;color:#cfdbe6">Total</div>
              <div style="font-size:10.5px;color:#7d93a8">inc. GST</div>
            </div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:28px;font-weight:600;color:#ffffff;letter-spacing:-0.03em">{{ vm.total }}</div>
          </div>
        </div>
        <div style="flex:none;padding:8px 16px 14px;display:grid;gap:8px;background:#fbfcfe">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px">
            <div @click="vm.hold" style="height:46px;border-radius:10px;border:1px solid #dfe6ee;background:#ffffff;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">
              <div style="font-size:13px;font-weight:600">Hold</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;color:#8b98a8">F4</div>
            </div>
            <div @click="vm.openDisc" style="height:46px;border-radius:10px;border:1px solid #dfe6ee;background:#ffffff;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">
              <div style="font-size:13px;font-weight:600">Discount</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;color:#8b98a8">{{ vm.discHint }}</div>
            </div>
            <div @click="vm.openVoid" style="height:46px;border-radius:10px;border:1px solid #f3cfcf;background:#fdf4f4;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer" v-pseudo:hover="'background:#fdeaea'">
              <div style="font-size:13px;font-weight:600;color:#b02020">Clear</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;color:#c77a7a">{{ vm.lineCountHint }}</div>
            </div>
          </div>
          <div @click="vm.openPay" :style="vm.payBtnStyle">
            <div style="font-size:16px;font-weight:700">{{ vm.payBtnLabel }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;background:rgba(255,255,255,.18);border-radius:5px;padding:2px 7px">F9</div>
          </div>
        </div>
      </div>
    </div>
    <template v-if="vm.kpOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:20px">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px">
          <div style="min-width:0">
            <div style="font-size:16px;font-weight:700;letter-spacing:-0.01em">{{ vm.kp.title }}</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ vm.kp.sub }}</div>
          </div>
          <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="border:1.5px solid var(--c-primary);background:var(--c-primary-tint);border-radius:12px;padding:10px 14px;margin-bottom:10px">
          <div style="display:flex;align-items:baseline;justify-content:flex-end;gap:8px">
            <div style="font-family:'IBM Plex Mono',monospace;font-size:38px;font-weight:600;letter-spacing:-0.03em;line-height:1.1">{{ vm.kp.display }}</div>
            <div style="font-size:14px;color:#5d6b7d;font-weight:600">{{ vm.kp.unit }}</div>
          </div>
          <div style="display:flex;align-items:baseline;gap:8px;margin-top:2px">
            <div :style="vm.kp.stockStyle">{{ vm.kp.stock }}</div>
            <div style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:13px;color:var(--c-primary-text);font-weight:600">{{ vm.kp.preview }}</div>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin-bottom:10px">
          <template v-for="(pr, $index) in vm.kp.presets" :key="$index">
            <div @click="pr.onClick" style="height:36px;border-radius:9px;border:1px solid #dfe6ee;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'border-color:var(--c-primary);color:var(--c-primary)'">{{ pr.label }}</div>
          </template>
        </div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:14px">
          <template v-for="(k, $index) in vm.kpKeys" :key="$index">
            <div @click="k.onClick" style="height:56px;border-radius:12px;border:1px solid #dfe6ee;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:21px;font-weight:600;cursor:pointer;user-select:none;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'" v-pseudo:active="'background:#e2edfb'">{{ k.label }}</div>
          </template>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:8px">
          <div @click="vm.closeOverlay" style="height:48px;border-radius:11px;border:1px solid #dfe6ee;display:grid;place-items:center;font-size:13.5px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div @click="vm.kpConfirm" :style="vm.kp.confirmStyle">{{ vm.kp.confirmLabel }}</div>
        </div>
      </div>
    </template>
    <template v-if="vm.custOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:540px;max-height:calc(100% - 60px);background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);display:flex;flex-direction:column;overflow:hidden">
        <div style="padding:18px 20px 14px;border-bottom:1px solid #eaeff5"><div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
          <div>
            <div style="font-size:16px;font-weight:700">Choose customer</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">Group pricing applies as soon as you pick</div>
          </div>
          <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div> <input :value="vm.custQ" @input="vm.onCustQ" placeholder="Name or phone number" style="width:100%;height:42px;border:1px solid #dfe6ee;border-radius:10px;padding:0 13px;font-family:'Public Sans',sans-serif;font-size:14px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
        <!-- Add a new customer: name and mobile, "Show more" for the rest -->
        <div v-if="newCust.open" style="flex:1;overflow-y:auto;min-height:0;padding:16px 20px">
          <div style="font-size:14px;font-weight:700;margin-bottom:12px">New customer</div>
          <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:10px">
            <label :style="ncLabel"><span>Name <span style="color:#d93a3a">*</span></span><input v-model="newCust.name" placeholder="Full name or business" :style="ncInput" /></label>
            <label :style="ncLabel"><span>Mobile <span style="color:#d93a3a">*</span></span><input v-model="newCust.phone" placeholder="04xx xxx xxx" @keydown.enter="saveNewCust" :style="ncInput + 'font-family:\'IBM Plex Mono\',monospace'" /></label>
          </div>
          <div @click="newCust.more = !newCust.more" style="margin-top:12px;font-size:12.5px;font-weight:600;color:var(--c-primary);cursor:pointer;display:inline-block">{{ newCust.more ? 'Show less' : 'Show more (email, group, address)' }}</div>
          <div v-if="newCust.more" style="display:grid;gap:10px;margin-top:12px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
              <label :style="ncLabel">Email<input v-model="newCust.email" placeholder="name@email.com" :style="ncInput" /></label>
              <label :style="ncLabel">Customer group
                <select v-model="newCust.customer_group_id" :style="ncInput">
                  <option :value="null">Retail (no group)</option>
                  <option v-for="g in vm.custGroups" :key="g.id" :value="g.id">{{ g.name }}{{ g.discount ? ' · ' + g.discount + '% off' : '' }}</option>
                </select>
              </label>
            </div>
            <label :style="ncLabel">Street<input v-model="newCust.street" placeholder="14 Haldon Street" :style="ncInput" /></label>
            <div style="display:grid;grid-template-columns:1.4fr 0.8fr 0.8fr;gap:10px">
              <label :style="ncLabel">Suburb<input v-model="newCust.suburb" placeholder="Lakemba" :style="ncInput" /></label>
              <label :style="ncLabel">State
                <select v-model="newCust.state" :style="ncInput"><option :value="null">—</option><option v-for="st in ['NSW','VIC','QLD','WA','SA','TAS','ACT','NT']" :key="st">{{ st }}</option></select>
              </label>
              <label :style="ncLabel">Postcode<input v-model="newCust.postcode" placeholder="2195" :style="ncInput" /></label>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1.6fr;gap:10px">
              <label v-if="can('contacts.create')" :style="ncLabel">Credit limit<input v-model="newCust.credit_limit" placeholder="0.00" :style="ncInput + 'text-align:right'" /></label>
              <label :style="ncLabel">Note<input v-model="newCust.note" placeholder="Optional" :style="ncInput" /></label>
            </div>
          </div>
          <div v-if="newCust.error" style="margin-top:12px;background:#fdeaea;color:#b02020;border-radius:9px;padding:9px 12px;font-size:12.5px;display:flex;align-items:center;gap:10px">
            <div style="flex:1">{{ newCust.error }}</div>
            <div v-if="newCust.sameId" @click="vm.pickCustomer(newCust.sameId); newCust.open = false" style="font-weight:700;cursor:pointer;text-decoration:underline;flex:none">Pick them</div>
          </div>
        </div>
        <div v-else style="flex:1;overflow-y:auto;min-height:0;padding:6px 8px">
          <template v-for="(c, $index) in vm.custRows" :key="$index">
            <div @click="c.onClick" :style="c.style" v-pseudo:hover="'background:#f6f9fc'">
              <div style="width:36px;height:36px;border-radius:9px;background:var(--c-sidebar);color:#ffffff;display:grid;place-items:center;font-size:12px;font-weight:600;flex:none">{{ c.initials }}</div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13.5px;font-weight:600">{{ c.name }}</div>
                <div style="font-size:11.5px;color:#7d8b9c;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ c.sub }}</div>
              </div>
              <div style="text-align:right;flex:none">
                <div :style="c.rightStyle">{{ c.right }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;color:#9aa7b6">{{ c.rightSub }}</div>
              </div>
              <div :style="c.checkStyle">✓</div>
            </div>
          </template>
          <template v-if="vm.custNone">
            <div style="padding:28px;text-align:center;font-size:12.5px;color:#7d8b9c">No customer matches. <span @click="openNewCust" style="color:var(--c-primary);font-weight:600;cursor:pointer">Add them as a new customer</span></div>
          </template>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #eaeff5;display:flex;gap:8px;align-items:center">
          <template v-if="!newCust.open">
            <div @click="openNewCust" style="height:36px;padding:0 14px;border-radius:9px;border:1px dashed var(--c-primary);color:var(--c-primary);display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer">+ Add new customer</div>
            <div style="margin-left:auto;font-size:11.5px;color:#9aa7b6">Esc to close</div>
          </template>
          <template v-else>
            <div @click="newCust.open = false" style="height:36px;padding:0 14px;border-radius:9px;border:1px solid #dfe6ee;color:#4a5866;display:flex;align-items:center;font-size:13px;font-weight:600;cursor:pointer">Back to list</div>
            <div @click="saveNewCust" :style="'margin-left:auto;height:36px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer;' + (newCust.saving ? 'opacity:.6;pointer-events:none;' : '')">{{ newCust.saving ? 'Saving…' : 'Save & select' }}</div>
          </template>
        </div>
      </div>
    </template>
    <template v-if="vm.tableOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:580px;background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:20px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
          <div>
            <div style="font-size:16px;font-weight:700">Tables</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">Tap a free table to seat this order. Tap a held table to pick its order back up.</div>
          </div>
          <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px">
          <template v-for="(t, $index) in vm.tables" :key="$index">
            <div @click="t.onClick" :style="t.style" v-pseudo:hover="'border-color:var(--c-primary)'">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;letter-spacing:-0.02em">{{ t.n }}</div>
              <div style="display:flex;align-items:center;gap:6px;font-size:11.5px;font-weight:600"><div :style="t.dot"></div>{{ t.status }}</div>
            </div>
          </template>
        </div>
      </div>
    </template>
    <template v-if="vm.discOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:20px">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
          <div>
            <div style="font-size:16px;font-weight:700">Order discount</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ vm.discNote }}</div>
          </div>
          <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px;flex:none" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="display:flex;gap:4px;background:#eef2f6;border-radius:11px;padding:4px;margin-bottom:12px">
          <div @click="vm.discPct" :style="vm.discTabStyle(vm.discMode === 'pct')">Percentage %</div>
          <div @click="vm.discAmtMode" :style="vm.discTabStyle(vm.discMode === 'amt')">Fixed amount $</div>
        </div>
        <div v-if="vm.discMode === 'pct'" style="display:grid;grid-template-columns:repeat(5,1fr);gap:7px">
          <template v-for="(d, $index) in vm.discChoices" :key="$index">
            <div @click="d.onClick" :style="d.style">{{ d.label }}</div>
          </template>
        </div>
        <div v-else>
          <div style="display:flex;gap:8px">
            <div style="position:relative;flex:1">
              <span style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:#5d6b7d;font-weight:700">$</span>
              <input :value="vm.discInput" @input="vm.onDiscInput" @keydown.enter="vm.applyFix" type="number" min="0" step="0.01" placeholder="0.00" autofocus
                style="width:100%;height:52px;border:1px solid #dfe6ee;border-radius:11px;padding:0 14px 0 30px;font-size:18px;font-weight:700;font-family:'IBM Plex Mono',monospace;outline:none" />
            </div>
            <div @click="vm.applyFix" style="height:52px;padding:0 20px;border-radius:11px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-size:14px;font-weight:700;cursor:pointer">Apply</div>
          </div>
          <div @click="vm.removeDisc" style="margin-top:10px;font-size:12.5px;color:#5d6b7d;cursor:pointer;text-decoration:underline;display:inline-block">Remove discount</div>
        </div>
      </div>
    </template>
    <template v-if="vm.voidOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:400px;background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
        <div style="font-size:17px;font-weight:700;margin-bottom:8px">Clear this order?</div>
        <div style="font-size:13px;line-height:1.6;color:#5d6b7d;margin-bottom:20px;text-wrap:pretty">{{ vm.voidText }}</div>
        <div style="display:flex;gap:9px;justify-content:flex-end">
          <div @click="vm.closeOverlay" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Keep order</div>
          <div @click="vm.holdInstead" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:var(--c-primary-text);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Hold instead</div>
          <div @click="vm.confirmVoid" style="height:40px;padding:0 16px;border-radius:9px;background:#c62828;color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:#a81f1f'">Clear order</div>
        </div>
      </div>
    </template>
    <template v-if="vm.heldOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:420px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:18px 20px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
          <div>
            <div style="font-size:16px;font-weight:700">Held orders</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">Resuming holds whatever is on the register first</div>
          </div>
          <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="flex:1;overflow-y:auto;padding:14px 16px;display:grid;gap:10px;align-content:start">
          <template v-for="(h, $index) in vm.heldRows" :key="$index">
            <div style="border:1px solid #dfe6ee;border-radius:12px;padding:14px 15px">
              <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:4px">
                <div style="font-size:14px;font-weight:700">{{ h.label }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#8b98a8">#{{ h.no }}</div>
                <div style="margin-left:auto;font-family:'IBM Plex Mono',monospace;font-size:15px;font-weight:600">{{ h.total }}</div>
              </div>
              <div style="font-size:11.5px;color:#7d8b9c;margin-bottom:6px">{{ h.meta }}</div>
              <div style="font-size:12.5px;color:#4a5866;line-height:1.5;margin-bottom:12px">{{ h.items }}</div>
              <div style="display:flex;gap:8px">
                <div @click="h.discard" style="height:36px;padding:0 13px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:600;color:#b02020;cursor:pointer" v-pseudo:hover="'background:#fdf4f4'">Discard</div>
                <div @click="h.resume" style="flex:1;height:36px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:grid;place-items:center;font-size:12.5px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Resume</div>
              </div>
            </div>
          </template>
          <template v-if="vm.heldEmpty">
            <div style="border:1px dashed #cfdae6;border-radius:12px;padding:32px 20px;text-align:center">
              <div style="font-size:13px;font-weight:600;margin-bottom:4px">No held orders</div>
              <div style="font-size:12px;color:#7d8b9c">Press Hold (F4) to park an order and serve the next customer.</div>
            </div>
          </template>
        </div>
      </div>
    </template>
    <template v-if="vm.recentOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:420px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:18px 20px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
          <div>
            <div style="font-size:16px;font-weight:700">Recent sales</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">Today at this branch</div>
          </div>
          <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="flex:1;overflow-y:auto">
          <template v-for="(r, $index) in vm.recentRows" :key="$index">
            <div style="display:flex;align-items:center;gap:12px;padding:13px 20px;border-bottom:1px solid #f0f4f8">
              <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:baseline;gap:8px">
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600">#{{ r.no }}</div>
                  <div style="font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.cust }}</div>
                </div>
                <div style="font-size:11.5px;color:#7d8b9c;margin-top:2px">{{ r.meta }}</div>
              </div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600">{{ r.total }}</div>
              <div @click="r.open" style="height:32px;padding:0 11px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Open</div>
            </div>
          </template>
          <div v-if="vm.recentEmpty" style="padding:32px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No sales yet today.</div>
        </div>
      </div>
    </template>
    <template v-if="vm.payOpen">
      <div @click="vm.closeOverlay" style="position:absolute;inset:0;background:rgba(9,22,35,.55);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:880px;max-width:calc(100% - 40px);height:620px;max-height:calc(100% - 40px);background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.7);overflow:hidden;display:grid;grid-template-columns:minmax(0,1fr) 320px">
        <div style="padding:22px 24px;overflow-y:auto;min-height:0">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <div>
              <div style="font-size:17px;font-weight:700;letter-spacing:-0.015em">Take payment</div>
              <div style="font-size:12px;color:#5d6b7d;margin-top:2px">{{ vm.payWho }}</div>
            </div>
            <div @click="vm.closeOverlay" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
          </div>
          <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:18px">
            <template v-for="(t, $index) in vm.tenders" :key="$index">
              <div @click="t.onClick" :style="t.style">
                <div style="font-size:14px;font-weight:700">{{ t.name }}</div>
                <div style="font-size:11px;opacity:.75;margin-top:2px">{{ t.sub }}</div>
              </div>
            </template>
          </div>
          <template v-if="vm.coinBox.show">
            <div @click="vm.coinBox.onClick" :style="vm.coinBox.style">
              <div style="width:30px;height:30px;border-radius:50%;background:#f5b301;color:#5a3d00;display:grid;place-items:center;font-size:12px;font-weight:800;flex:none">C</div>
              <div style="flex:1;min-width:0">
                <div style="font-size:13.5px;font-weight:700">{{ vm.coinBox.label }}</div>
                <div style="font-size:11.5px;opacity:.75;margin-top:1px">{{ vm.coinBox.sub }}</div>
              </div>
              <div style="font-size:12px;font-weight:600">{{ vm.coinBox.action }}</div>
            </div>
          </template>
          <div style="border:1px solid #e4eaf1;border-radius:12px;padding:10px 12px;margin-bottom:16px;background:#fbfcfe">
            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
              <div style="font-size:12.5px;font-weight:700;margin-right:4px">Tip</div>
              <template v-for="(c, $index) in vm.tipBox.chips" :key="$index">
                <div @click="c.onClick" :style="c.style">{{ c.label }}</div>
              </template>
              <input :value="vm.tipBox.value" @input="vm.tipBox.onInput" inputmode="decimal" placeholder="Other $" title="Tip amount" style="width:78px;height:32px;border:1px solid #dfe6ee;border-radius:8px;padding:0 8px;font-family:'IBM Plex Mono',monospace;font-size:13px;text-align:right;outline:none" />
            </div>
            <div v-if="vm.tipBox.hasTip" style="display:flex;align-items:center;gap:8px;margin-top:8px">
              <select :value="vm.tipBox.staffId" @change="vm.tipBox.onStaff" title="Tip is for" :style="vm.tipBox.staffStyle">
                <option :value="0">Tip is for… (choose staff)</option>
                <option v-for="m in vm.tipBox.staff" :key="m.id" :value="m.id">{{ m.name }}{{ m.here ? '' : ' · other branch' }}</option>
              </select>
              <div style="display:flex;border:1px solid #dfe6ee;border-radius:8px;overflow:hidden;background:#ffffff;flex:none">
                <template v-for="(m, $index) in vm.tipBox.methods" :key="$index">
                  <div @click="m.onClick" :style="m.style">{{ m.name }}</div>
                </template>
              </div>
            </div>
            <div style="font-size:11px;color:#7d8b9c;margin-top:6px">{{ vm.tipBox.note }}</div>
          </div>
          <template v-if="vm.tenderCash">
            <div style="display:flex;align-items:baseline;margin-bottom:6px">
              <div style="font-size:12px;font-weight:600">Cash tendered</div>
              <div style="margin-left:auto;font-size:11.5px;color:#7d8b9c">Cash is rounded to the nearest 5c</div>
            </div>
            <div style="height:58px;border:1.5px solid var(--c-primary);border-radius:12px;padding:0 16px;display:flex;align-items:center;justify-content:flex-end;font-family:'IBM Plex Mono',monospace;font-size:28px;font-weight:600;background:var(--c-primary-tint);margin-bottom:10px;letter-spacing:-0.02em">{{ vm.cashDisplay }}</div>
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:7px;margin-bottom:12px">
              <template v-for="(q, $index) in vm.quickCash" :key="$index">
                <div @click="q.onClick" :style="q.style" v-pseudo:hover="'border-color:var(--c-primary);color:var(--c-primary)'">{{ q.label }}</div>
              </template>
            </div>
          </template>
          <template v-if="vm.tenderSplit">
            <div style="display:grid;gap:7px;margin-bottom:12px">
              <template v-for="(sr, $index) in vm.splitRows" :key="$index">
                <div style="display:flex;align-items:center;gap:10px;border:1px solid #dfe6ee;border-radius:10px;padding:9px 12px"><div style="font-size:13px;font-weight:600;flex:1">{{ sr.method }}</div> <div style="font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600">{{ sr.amt }}</div> <span style="background:#e7f6ee;color:#0f6b43;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600">{{ sr.state }}</span> <div @click="sr.remove" style="width:24px;height:24px;border-radius:6px;display:grid;place-items:center;color:#b3c0cf;cursor:pointer;font-size:13px" v-pseudo:hover="'background:#fdeaea;color:#b02020'">×</div></div>
              </template>
            </div>
            <div style="display:flex;gap:6px;margin-bottom:8px">
              <template v-for="(m, $index) in vm.splitMethods" :key="$index">
                <div @click="m.onClick" :style="m.style">{{ m.name }}</div>
              </template>
            </div>
            <div style="height:52px;border:1.5px solid var(--c-primary);border-radius:12px;padding:0 16px;display:flex;align-items:center;justify-content:space-between;background:var(--c-primary-tint);margin-bottom:10px">
              <div style="font-size:12px;color:#5d6b7d">{{ vm.splitHint }}</div>
              <div :style="vm.splitDisplayStyle">{{ vm.splitDisplay }}</div>
            </div>
          </template>
          <template v-if="vm.showPayKeypad">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
              <template v-for="(k, $index) in vm.payKeys" :key="$index">
                <div @click="k.onClick" style="height:52px;border-radius:12px;border:1px solid #dfe6ee;display:grid;place-items:center;font-family:'IBM Plex Mono',monospace;font-size:20px;font-weight:600;cursor:pointer;user-select:none" v-pseudo:hover="'background:#f2f6fa'" v-pseudo:active="'background:#e2edfb'">{{ k.label }}</div>
              </template>
            </div>
            <template v-if="vm.tenderSplit">
              <div @click="vm.addSplit" :style="vm.addSplitStyle">{{ vm.addSplitLabel }}</div>
            </template>
          </template>
          <template v-if="vm.tenderCard">
            <div :style="vm.card.box">
              <div :style="vm.card.icon">EFT</div>
              <div style="font-size:16px;font-weight:700;margin-bottom:5px">{{ vm.card.title }}</div>
              <div style="font-size:12.5px;line-height:1.55;max-width:380px;margin:0 auto 18px;text-wrap:pretty">{{ vm.card.body }}</div>
              <template v-if="vm.card.showSend">
                <div style="display:flex;gap:8px;justify-content:center">
                  <div @click="vm.confirmCard" style="height:44px;padding:0 20px;border-radius:11px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:14px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ vm.card.sendLabel }}</div>
                </div>
              </template>
            </div>
          </template>
          <template v-if="vm.tenderAccount">
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">
              <template v-for="(a, $index) in vm.acctStats" :key="$index">
                <div style="border:1px solid #e4eaf1;border-radius:11px;padding:12px 13px">
                  <div style="font-size:11px;color:#7d8b9c;margin-bottom:5px">{{ a.k }}</div>
                  <div :style="a.vStyle">{{ a.v }}</div>
                </div>
              </template>
            </div>
            <div :style="vm.acct.box">
              <div style="font-weight:700;flex:none">{{ vm.acct.mark }}</div>
              <div style="flex:1">{{ vm.acct.msg }}</div>
            </div>
            <template v-if="vm.acct.showPick">
              <div @click="vm.pickFromPay" style="margin-top:10px;height:40px;padding:0 16px;border-radius:10px;border:1px solid #dfe6ee;display:inline-flex;align-items:center;font-size:13px;font-weight:600;color:var(--c-primary);cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">Choose customer</div>
            </template>
          </template>
        </div>
        <div style="background:#0f1c2b;color:#ffffff;padding:22px;display:flex;flex-direction:column;min-height:0">
          <div style="font-size:12px;color:#8fa3b6">Order #{{ vm.orderNo }} · {{ vm.itemCount }} items</div>
          <div style="display:grid;gap:8px;font-size:12.5px;color:#9fb2c4;margin-top:14px">
            <template v-for="(r, $index) in vm.paySummary" :key="$index">
              <div style="display:flex;gap:10px">
                <div style="flex:1">{{ r.k }}</div>
                <div :style="r.vStyle">{{ r.v }}</div>
              </div>
            </template>
          </div>
          <div style="border-top:1px solid rgba(255,255,255,.12);margin-top:14px;padding-top:14px">
            <div style="font-size:12px;color:#9fb2c4">{{ vm.dueLabel }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:36px;font-weight:600;letter-spacing:-0.03em;line-height:1.15">{{ vm.dueValue }}</div>
            <div v-if="vm.dueLabel2" style="font-size:11.5px;color:#86dcaa;margin-top:2px">{{ vm.dueLabel2 }}</div>
          </div>
          <div :style="vm.changeBox">
            <div style="font-size:12px;opacity:.85">{{ vm.changeLabel }}</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:24px;font-weight:600;letter-spacing:-0.02em">{{ vm.changeValue }}</div>
          </div>
          <div style="margin-top:auto;padding-top:18px;display:grid;gap:8px">
            <div v-if="vm.payError" style="background:rgba(217,58,58,.22);color:#ffb3b3;border-radius:10px;padding:9px 12px;font-size:12px;line-height:1.45">{{ vm.payError }}</div>
            <div @click="vm.complete" :style="vm.completeStyle">{{ vm.completeLabel }}</div>
            <div style="font-size:11px;color:#6f8599;text-align:center">Enter completes · Esc goes back</div>
          </div>
        </div>
      </div>
    </template>
    <template v-if="invoiceSale">
      <div @click="invoiceSale = null" style="position:absolute;inset:0;background:rgba(9,22,35,.55);z-index:40"></div>
      <div :style="'position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);max-width:calc(100% - 40px);height:calc(100% - 40px);background:#ffffff;border-radius:18px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.7);display:flex;flex-direction:column;overflow:hidden;width:' + (invoiceRoll ? '420px' : '820px')">
        <div style="padding:14px 18px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:10px">
          <div>
            <div style="font-size:15px;font-weight:700">Invoice {{ invoiceSale.ref }}</div>
            <div style="font-size:11.5px;color:#7d8b9c">{{ invoiceRoll ? '80mm roll' : 'A4' }} · from Settings › Invoice &amp; receipt</div>
          </div>
          <div @click="printAndNewSale" title="Prints the invoice and starts a new sale" style="margin-left:auto;height:36px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">Print<span style="font-family:'IBM Plex Mono',monospace;font-size:10px;background:rgba(255,255,255,.2);border-radius:5px;padding:2px 6px">Enter</span></div>
          <div @click="invoiceSale = null" title="Close" style="width:36px;height:36px;border-radius:9px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="flex:1;min-height:0;background:#eef2f6;padding:14px;display:flex;justify-content:center">
          <iframe :srcdoc="invoiceHtml(invoiceSale)" title="Invoice preview" :style="'border:0;background:#ffffff;height:100%;box-shadow:0 2px 10px rgba(9,22,35,.12);width:' + (invoiceRoll ? '320px' : '100%')"></iframe>
        </div>
      </div>
    </template>
    <template v-if="vm.doneOpen">
      <div style="position:absolute;inset:0;background:rgba(9,22,35,.55);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:480px;max-height:calc(100% - 40px);background:#ffffff;border-radius:18px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.7);display:flex;flex-direction:column;overflow:hidden">
        <div style="padding:24px 24px 16px;text-align:center">
          <div style="width:50px;height:50px;border-radius:50%;background:#2f9e63;color:#ffffff;display:grid;place-items:center;font-size:22px;margin:0 auto 12px">✓</div>
          <div style="font-size:18px;font-weight:700;letter-spacing:-0.015em">{{ vm.done.title }}</div>
          <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">{{ vm.done.line }}</div>
        </div>
        <template v-if="vm.done.showChange">
          <div style="margin:0 24px 14px;background:#e7f6ee;border:1px solid #bfe5d1;border-radius:12px;padding:12px 16px;display:flex;align-items:center">
            <div style="font-size:13px;font-weight:600;color:#0f6b43;flex:1">Change to give</div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:26px;font-weight:600;color:#0f6b43;letter-spacing:-0.02em">{{ vm.done.change }}</div>
          </div>
        </template>
        <div style="margin:0 24px;border:1px solid #e4eaf1;border-radius:12px;padding:14px 16px;overflow-y:auto;min-height:0;background:#fbfcfe">
          <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#7d8b9c;margin-bottom:8px">{{ vm.done.head }}</div>
          <template v-for="(dl, $index) in vm.done.lines" :key="$index">
            <div style="display:flex;gap:10px;padding:5px 0;font-size:12.5px;border-bottom:1px dashed #e4eaf1">
              <div style="font-family:'IBM Plex Mono',monospace;color:#5d6b7d;min-width:60px">{{ dl.qty }}</div>
              <div style="flex:1;min-width:0">{{ dl.name }}<div :style="dl.noteStyle">{{ dl.note }}</div></div>
              <div style="font-family:'IBM Plex Mono',monospace;font-weight:500">{{ dl.total }}</div>
            </div>
          </template>
          <div style="display:grid;gap:4px;margin-top:10px">
            <template v-for="(dr, $index) in vm.done.rows" :key="$index">
              <div :style="dr.style">
                <div style="flex:1">{{ dr.k }}</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ dr.v }}</div>
              </div>
            </template>
          </div>
        </div>
        <div style="padding:16px 24px 22px;display:grid;grid-template-columns:1fr 1.5fr;gap:8px">
          <div @click="vm.newSale" style="height:46px;border-radius:11px;border:1px solid #dfe6ee;display:flex;align-items:center;justify-content:center;gap:9px;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer" v-pseudo:hover="'background:#f2f6fa'">New sale<span style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;background:#eef2f6;border-radius:5px;padding:2px 6px">Esc</span></div>
          <div @click="openInvoice" title="Enter shows the invoice, Enter again prints it and starts a new sale" style="height:46px;border-radius:11px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;justify-content:center;gap:9px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 0 0 3px rgba(22,104,201,.25)" v-pseudo:hover="'background:var(--c-primary-hover)'">Print invoice<span style="font-family:'IBM Plex Mono',monospace;font-size:10.5px;background:rgba(255,255,255,.2);border-radius:5px;padding:2px 6px">Enter</span></div>
        </div>
      </div>
    </template>
    <PosSignIn @signed-in="onSignedIn" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue';
import { usePosPage } from '../erp/useErpPage';
import { invoiceHtml, printInvoice } from '../api/invoice.js';
import CashRegister from './pos/CashRegister.vue';
import PosSignIn from './pos/PosSignIn.vue';
import { can, posSignOut } from '../api/auth.js';
import { api } from '../api/http.js';

const vm = usePosPage();

// "Add new customer" in the Choose customer window: name and mobile, "Show more" for the rest.
// POST /api/pos/customers; the new customer is picked for the order straight away.
const ncLabel = 'display:grid;gap:5px;font-size:12px;font-weight:600;color:#4a5866';
const ncInput = "width:100%;height:38px;border:1px solid #dfe6ee;border-radius:9px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13.5px;font-weight:400;color:#0f1c2b;outline:none;background:#ffffff;";
const emptyCust = () => ({ open: false, more: false, saving: false, error: '', sameId: 0, name: '', phone: '', email: '', customer_group_id: null as number | null, street: '', suburb: '', state: null as string | null, postcode: '', credit_limit: '', note: '' });
const newCust = reactive(emptyCust());

// What was typed in the search goes into the form: digits as the mobile, words as the name.
function openNewCust() {
  const q = (vm.value.custQ || '').trim();
  Object.assign(newCust, emptyCust(), { open: true });
  if (/^[\d\s+()-]{6,}$/.test(q)) newCust.phone = q; else newCust.name = q;
}

async function saveNewCust() {
  if (newCust.saving) return;
  if (!newCust.name.trim()) { newCust.error = 'Enter the customer\u2019s name.'; return; }
  if (newCust.phone.replace(/\D/g, '').length < 8) { newCust.error = 'Enter a mobile number.'; return; }
  newCust.saving = true;
  newCust.error = '';
  newCust.sameId = 0;
  try {
    const row = await api.post('/pos/customers', {
      branch_id: vm.value.branchId, name: newCust.name.trim(), phone: newCust.phone.trim(),
      email: newCust.email.trim() || null, customer_group_id: newCust.customer_group_id,
      street: newCust.street.trim() || null, suburb: newCust.suburb.trim() || null, state: newCust.state,
      postcode: newCust.postcode.trim() || null, credit_limit: parseFloat(newCust.credit_limit) || 0, note: newCust.note.trim() || null,
    });
    newCust.open = false;
    vm.value.addCustomer(row);
  } catch (e: any) {
    newCust.error = e.message || 'Could not add the customer.';
    // The mobile is already a customer: offer to pick them.
    const digits = newCust.phone.replace(/\D/g, '');
    const found = vm.value.custAll.find((c: any) => String(c.phone || '').replace(/\D/g, '') === digits);
    newCust.sameId = found ? found.id : 0;
  }
  newCust.saving = false;
}

// The form closes when the Choose customer window closes.
watch(() => vm.value.custOpen, open => { if (!open) newCust.open = false; });

// Customer display (views/CustomerDisplay.vue): the order is sent to it on a BroadcastChannel every time it changes.
// It works on the same computer: open it with the button and drag the window to the customer's screen.
const display = new BroadcastChannel('customer-display');

function displayData() {
  const v = vm.value;
  const rows: string[][] = [['Items', v.itemsTotal]];
  if (v.showGroup) rows.push([v.groupLabel, '\u2212' + v.groupAmt]);
  if (v.discAmt && v.discAmt !== 'Add') rows.push(['Discount', v.discAmt]);
  if (v.showFee) rows.push(['Delivery', v.feeAmt]);
  const d = v.doneOpen ? v.doneSale : null;
  return {
    branch: v.branchName,
    customer: v.custChip ? v.custChip.name : '',
    lines: (v.lines || []).map((l: any) => ({ name: l.name, qty: l.qty, price: l.meta, total: l.total, discount: l.discTagStyle && l.discTagStyle !== 'display:none' ? l.discTag + ' off' : '' })),
    rows,
    total: v.total,
    gst: v.gst,
    // After payment: thank you, total paid and the change
    done: d ? {
      ref: d.ref, method: d.method,
      total: Number(d.total).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      change: d.change_given > 0.001 ? Number(d.change_given).toLocaleString('en-AU', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '',
    } : null,
  };
}

function sendToDisplay() {
  display.postMessage(JSON.parse(JSON.stringify(displayData())));
}

// Send when the order changes, and when a display that just opened asks for it.
watch(() => JSON.stringify(displayData()), sendToDisplay);
display.onmessage = (e) => { if (e.data === 'hello') sendToDisplay(); };
onUnmounted(() => display.close());

function openCustomerDisplay() {
  window.open(window.location.pathname + '#/customer-display', 'customer-display', 'width=1280,height=800');
}

// Sign out / switch user: the sign-in window opens over this screen (PosSignIn), so the order is kept.
const userMenu = ref(false);
// A new key when someone else signs in, so the cash register reloads for them.
const userKey = ref(0);

async function signOut() {
  userMenu.value = false;
  await posSignOut();
}

// Another cashier signed in: their branch, prices and cash register are loaded again.
function onSignedIn(info: { changed: boolean }) {
  if (!info.changed) return;
  userKey.value++;
  vm.value.retryCatalog();
}

// Print on the "Sale complete" screen: shows the invoice laid out from Settings > Invoice & receipt, then prints it.
const invoiceSale = ref<any>(null);
const invoiceRoll = computed(() => !invoiceSale.value || !invoiceSale.value.invoice || invoiceSale.value.invoice.paper !== 'A4');

function openInvoice() {
  invoiceSale.value = vm.value.doneSale;
}

// Print, close the invoice, and get the POS ready for the next sale.
function printAndNewSale() {
  const sale = invoiceSale.value;
  invoiceSale.value = null;
  if (sale) printInvoice(sale);
  vm.value.newSale();
}

// Keys after a sale: on "Sale complete" Enter shows the invoice (Esc = new sale, handled by the POS).
// On the invoice: Enter prints it and starts a new sale, Esc goes back to "Sale complete".
function onKeyFirst(e: KeyboardEvent) {
  if (invoiceSale.value) {
    if (e.key === 'Escape' || e.key === 'Enter') {
      e.stopImmediatePropagation();
      e.preventDefault();
      if (e.key === 'Escape') invoiceSale.value = null;
      else printAndNewSale();
    }
    return;
  }
  if (vm.value.doneOpen && e.key === 'Enter') {
    e.stopImmediatePropagation();
    e.preventDefault();
    openInvoice();
  }
}
onMounted(() => window.addEventListener('keydown', onKeyFirst, true));
onUnmounted(() => window.removeEventListener('keydown', onKeyFirst, true));

// On-screen keypad for the search box (Settings > Sales & POS > On-screen keypad). Open or closed is remembered on this computer.
const KB_ROWS = [
  ['1', '2', '3', '4', '5', '6', '7', '8', '9', '0'],
  ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
  ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L', 'BACK'],
  ['Z', 'X', 'C', 'V', 'B', 'N', 'M', '-', '.', '/'],
];

function savedKb() {
  try { return localStorage.getItem('pos_kb') === '1'; } catch { return false; }
}
const kbOpen = ref(savedKb());

function toggleKb() {
  kbOpen.value = !kbOpen.value;
  try { localStorage.setItem('pos_kb', kbOpen.value ? '1' : '0'); } catch { /* ignore */ }
}

const kbBtnStyle = computed(() => 'width:50px;height:50px;border-radius:12px;display:grid;place-items:center;font-size:22px;cursor:pointer;flex:none;user-select:none;'
  + (kbOpen.value ? 'background:var(--c-primary);color:#ffffff;' : 'border:1px solid #dfe6ee;background:#ffffff;color:#4a5866;'));

function keyStyle(k: string) {
  const base = 'height:44px;border-radius:9px;display:grid;place-items:center;font-size:15px;font-weight:600;cursor:pointer;';
  if (k === 'ENTER') return base + 'background:var(--c-primary);color:#ffffff;';
  if (k === 'BACK' || k === 'Clear' || k === 'Hide') return base + 'background:#eef2f6;color:#4a5866;font-size:13px;';
  return base + "border:1px solid #dfe6ee;background:#ffffff;color:#0f1c2b;font-family:'IBM Plex Mono',monospace;";
}

// Each key changes the search text the same way typing does, so the product list filters as you go.
function press(k: string) {
  const q = vm.value.q || '';
  if (k === 'ENTER') { vm.value.onSearchKey({ key: 'Enter' }); return; }
  let next = q;
  if (k === 'BACK') next = q.slice(0, -1);
  else if (k === 'CLEAR') next = '';
  else next = q + k.toLowerCase();
  vm.value.onSearch({ target: { value: next } });
}

// Grid or list view for the product area. Remembered on this computer.
function savedView() {
  try { return localStorage.getItem('pos_view') === 'list' ? 'list' : 'grid'; } catch { return 'grid'; }
}
const view = ref(savedView());

function setView(v: string) {
  view.value = v;
  try { localStorage.setItem('pos_view', v); } catch { /* ignore */ }
}

// How many products in one grid row: 'auto' fits as many as the screen allows.
function savedCols() {
  try { return localStorage.getItem('pos_cols') || 'auto'; } catch { return 'auto'; }
}
const cols = ref(savedCols());

function setCols(v: string) {
  cols.value = v;
  try { localStorage.setItem('pos_cols', v); } catch { /* ignore */ }
}

function viewBtn(v: string) {
  const on = view.value === v;
  return 'height:30px;padding:0 11px;display:flex;align-items:center;font-size:12px;font-weight:600;cursor:pointer;user-select:none;'
    + (on ? 'background:var(--c-primary);color:#ffffff;' : 'color:#5d6b7d;');
}

function listRow(p: any) {
  return 'background:#ffffff;border:1px solid #dfe6ee;border-radius:10px;padding:9px 14px;cursor:pointer;display:flex;align-items:center;gap:12px;user-select:none;'
    + (p.hasCart ? 'border-color:var(--c-chart);box-shadow:inset 0 0 0 1px var(--c-chart);' : '')
    + (p.out ? 'opacity:.5;cursor:not-allowed;' : '');
}
</script>
