<template>
  <div style="height:100%;display:flex">
    <div style="flex:1;min-width:0;overflow-y:auto">
      <div style="padding:18px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>Orders</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">All orders</span></div>
        <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">Orders</h2>
            <div style="font-size:12.5px;color:#5d6b7d">{{ vm.od_sub }}</div>
          </div>
          <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
            <div @click="vm.od_goKitchen" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Kitchen display</div>
            <div @click="vm.od_newOrder" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">+ New order</div>
          </div>
        </div>
        <div style="display:flex;gap:2px;margin-top:14px;overflow-x:auto;scrollbar-width:none">
          <template v-for="(t, $index) in vm.od_tabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.countStyle">{{ t.count }}</span></div>
          </template>
        </div>
      </div>
      <div style="padding:16px 24px 32px;display:grid;gap:14px">
        <div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px">
          <template v-for="(k, $index) in vm.od_kpis" :key="$index">
            <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;padding:13px 16px">
              <div style="font-size:11.5px;color:#7d8b9c">{{ k.k }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:21px;font-weight:600;letter-spacing:-0.02em;margin-top:2px">{{ k.v }}</div>
              <div style="font-size:11.5px;color:#8b98a8;margin-top:1px">{{ k.n }}</div>
            </div>
          </template>
        </div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <div style="position:relative;width:240px"><div style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9aa7b6;font-size:12px">⚲</div> <input :value="vm.od_q" @input="vm.od_onQ" placeholder="Order no, customer or item" style="width:100%;height:34px;border:1px solid #dfe6ee;border-radius:8px;background:#ffffff;padding:0 10px 0 29px;font-family:'Public Sans',sans-serif;font-size:12.5px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <div style="display:flex;gap:3px;background:#e4eaf1;padding:3px;border-radius:9px">
            <template v-for="(c, $index) in vm.od_chans" :key="$index">
              <div @click="c.onClick" :style="c.style">{{ c.name }}</div>
            </template>
          </div>
          <div @click="vm.od_toggleUnpaid" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-left:auto">
            <div :style="vm.od_upSw">
              <div :style="vm.od_upKnob"></div>
            </div>
            <div style="font-size:12.5px;color:#4a5866">Unpaid only</div>
          </div>
        </div>
        <div style="background:#ffffff;border:1px solid #dfe6ee;border-radius:12px;overflow:hidden">
          <div style="display:grid;grid-template-columns:0.7fr 0.6fr 1.6fr 1.1fr 0.5fr 0.9fr 0.9fr 1fr;gap:12px;padding:10px 16px;background:#f6f9fc;border-bottom:1px solid #dfe6ee;font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;align-items:center">
            <div>Order</div>
            <div>Time</div>
            <div>Customer</div>
            <div>Channel</div>
            <div style="text-align:right">Items</div>
            <div style="text-align:right">Total</div>
            <div>Payment</div>
            <div>Status</div>
          </div>
          <template v-for="(r, $index) in vm.od_rows" :key="$index">
            <div @click="r.open" :style="r.style" v-pseudo:hover="'background:var(--c-primary-tint)'">
              <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600;color:var(--c-primary)">{{ r.no }}</div>
              <div style="min-width:0">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12px">{{ r.time }}</div>
                <div :style="r.ageStyle">{{ r.age }}</div>
              </div>
              <div style="min-width:0">
                <div style="font-size:13px;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.cust }}</div>
                <div style="font-size:11px;color:#8b98a8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ r.where }}</div>
              </div>
              <div><span :style="r.chStyle">{{ r.ch }}</span></div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right">{{ r.items }}</div>
              <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right;font-weight:600">{{ r.total }}</div>
              <div><span :style="r.payBadge">{{ r.pay }}</span></div>
              <div><span :style="r.stBadge">{{ r.status }}</span></div>
            </div>
          </template>
          <template v-if="vm.od_noRows">
            <div style="padding:36px 20px;text-align:center;font-size:12.5px;color:#7d8b9c">No orders match.</div>
          </template>
        </div>
      </div>
    </div>

    <template v-if="vm.od_hasSel">
      <div style="width:380px;flex:none;background:#ffffff;border-left:1px solid #dfe6ee;display:flex;flex-direction:column;min-height:0">
        <div style="padding:16px 20px;border-bottom:1px solid #eaeff5">
          <div style="display:flex;align-items:center;gap:9px"><div style="font-family:'IBM Plex Mono',monospace;font-size:19px;font-weight:700">{{ vm.od_d.no }}</div> <span :style="vm.od_d.chStyle">{{ vm.od_d.ch }}</span> <span :style="vm.od_d.stBadge">{{ vm.od_d.status }}</span> <div @click="vm.od_closeSel" style="margin-left:auto;width:30px;height:30px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:14px" v-pseudo:hover="'background:#f2f6fa'">×</div></div>
          <div style="font-size:13px;font-weight:600;margin-top:8px">{{ vm.od_d.cust }}</div>
          <div style="font-size:12px;color:#7d8b9c;margin-top:1px">{{ vm.od_d.where }}</div>
        </div>
        <div style="flex:1;overflow-y:auto;padding:16px 20px;display:grid;gap:16px;align-content:start">
          <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:4px">
            <template v-for="(s, $index) in vm.od_d.steps" :key="$index">
              <div style="display:grid;gap:5px">
                <div :style="s.bar"></div>
                <div :style="s.lbl">{{ s.name }}</div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:10px;color:#9aa7b6">{{ s.at }}</div>
              </div>
            </template>
          </div>
          <div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;margin-bottom:6px">Items</div>
            <template v-for="(i, $index) in vm.od_d.items" :key="$index">
              <div style="display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #f0f4f8;align-items:flex-start">
                <div style="font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:700;color:var(--c-primary);min-width:24px">{{ i.q }}×</div>
                <div style="flex:1;min-width:0">
                  <div style="font-size:13px;font-weight:500">{{ i.name }}</div>
                  <div :style="i.noteStyle">{{ i.note }}</div>
                </div>
                <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px">{{ i.total }}</div>
              </div>
            </template>
          </div>
          <div style="display:grid;gap:6px;font-size:12.5px">
            <template v-for="(s, $index) in vm.od_d.sums" :key="$index">
              <div :style="s.style">
                <div style="flex:1">{{ s.k }}</div>
                <div style="font-family:'IBM Plex Mono',monospace">{{ s.v }}</div>
              </div>
            </template>
          </div>
          <div :style="vm.od_d.payBox">
            <div style="flex:1">
              <div style="font-size:12.5px;font-weight:700">{{ vm.od_d.payTitle }}</div>
              <div style="font-size:11.5px;margin-top:1px;opacity:.85">{{ vm.od_d.paySub }}</div>
            </div>
            <template v-if="vm.od_d.canPay">
              <div @click="vm.od_openPay" style="height:32px;padding:0 13px;border-radius:8px;background:#ffffff;border:1px solid currentColor;display:flex;align-items:center;font-size:12.5px;font-weight:700;cursor:pointer">Take payment</div>
            </template>
          </div>
          <template v-if="vm.od_d.hasNote">
            <div style="background:#fdf1dd;color:#8a5300;border-radius:9px;padding:9px 12px;font-size:12.5px;font-weight:600">{{ vm.od_d.note }}</div>
          </template>
          <div>
            <div style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#5d6b7d;margin-bottom:8px">Activity</div>
            <div style="display:grid;gap:9px">
              <template v-for="(a, $index) in vm.od_d.log" :key="$index">
                <div style="display:flex;gap:10px">
                  <div :style="a.dot"></div>
                  <div style="font-size:12.5px;flex:1">{{ a.what }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace;font-size:11px;color:#9aa7b6">{{ a.when }}</div>
                </div>
              </template>
            </div>
          </div>
        </div>
        <div style="padding:12px 20px;border-top:1px solid #eaeff5;display:flex;gap:7px;flex-wrap:wrap">
          <template v-for="(a, $index) in vm.od_d.actions" :key="$index">
            <div @click="a.onClick" :style="a.style">{{ a.label }}</div>
          </template>
        </div>
      </div>
    </template>

    <!-- overlays -->
    <template v-if="vm.od_formOpen">
      <div @click="vm.od_closeAll" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:760px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:16px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
          <div>
            <div style="font-size:16px;font-weight:700">{{ vm.od_fTitle }}</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">Same order form as the POS. Kitchen items go to the kitchen display when sent.</div>
          </div>
          <div @click="vm.od_closeAll" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="flex:1;min-height:0;display:grid;grid-template-columns:minmax(0,1fr) 320px">
          <div style="overflow-y:auto;padding:16px 20px;display:grid;gap:14px;align-content:start;border-right:1px solid #eaeff5">
            <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
              <template v-for="(c, $index) in vm.od_fChans" :key="$index">
                <div @click="c.onClick" :style="c.style">{{ c.name }}</div>
              </template>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Customer</label> <select :value="vm.od_fCust" @change="vm.od_onCust" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
                <template v-for="(o, $index) in vm.od_custOpts" :key="$index">
                  <option :value="o.id">{{ o.name }}</option>
                </template>
              </select></div>
              <template v-if="vm.od_fIsDine">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Table</label> <select :value="vm.od_fTable" @change="vm.od_onTable" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
                  <template v-for="(o, $index) in vm.od_tableOpts" :key="$index">
                    <option :value="o.id">{{ o.name }}</option>
                  </template>
                </select></div>
              </template>
              <template v-if="vm.od_fIsDeliv">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Delivery address <span style="color:#d93a3a">*</span></label> <input :value="vm.od_fAddr" @input="vm.od_onAddr" placeholder="Street, suburb" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </template>
              <template v-if="vm.od_fIsPickup">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Pickup time</label> <select :value="vm.od_fPickup" @change="vm.od_onPickup" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
                  <option value="ASAP">As soon as possible</option>
                  <option value="15">In 15 min</option>
                  <option value="30">In 30 min</option>
                  <option value="60">In 1 hour</option>
                </select></div>
              </template>
            </div>
            <div>
              <div style="display:flex;gap:6px;margin-bottom:10px;flex-wrap:wrap">
                <template v-for="(c, $index) in vm.od_menuCats" :key="$index">
                  <div @click="c.onClick" :style="c.style">{{ c.name }}</div>
                </template>
              </div>
              <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px">
                <template v-for="(m, $index) in vm.od_menu" :key="$index">
                  <div @click="m.onClick" :style="m.style" v-pseudo:hover="'border-color:var(--c-primary);background:var(--c-primary-tint)'">
                    <div style="font-size:13px;font-weight:600;line-height:1.3">{{ m.name }}</div>
                    <div style="display:flex;align-items:center;margin-top:6px"><div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;color:#4a5866;flex:1">{{ m.price }}</div> <span :style="m.qStyle">{{ m.inCart }}</span></div>
                  </div>
                </template>
              </div>
            </div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Order note</label> <input :value="vm.od_fNote" @input="vm.od_onNote" placeholder="Shown on the kitchen ticket" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          </div>
          <div style="display:flex;flex-direction:column;min-height:0;background:#fbfcfe">
            <div style="padding:14px 16px 8px;font-size:13px;font-weight:700">{{ vm.od_cartTitle }}</div>
            <div style="flex:1;overflow-y:auto;padding:0 12px">
              <template v-for="(l, $index) in vm.od_cart" :key="$index">
                <div style="background:#ffffff;border:1px solid #e4eaf1;border-radius:10px;padding:9px 10px;margin-bottom:7px">
                  <div style="display:flex;align-items:flex-start;gap:8px">
                    <div style="flex:1;min-width:0;font-size:13px;font-weight:600">{{ l.name }}</div>
                    <div style="font-family:'IBM Plex Mono',monospace;font-size:12.5px;font-weight:600">{{ l.total }}</div>
                  </div>
                  <div style="display:flex;align-items:center;gap:8px;margin-top:7px"><div style="display:flex;align-items:center;border:1px solid #dfe6ee;border-radius:7px;overflow:hidden;height:30px">
                    <div @click="l.dec" style="width:30px;height:100%;display:grid;place-items:center;cursor:pointer;color:#4a5866" v-pseudo:hover="'background:#f2f6fa'">−</div>
                    <div style="width:28px;text-align:center;font-family:'IBM Plex Mono',monospace;font-size:13px;font-weight:600">{{ l.q }}</div>
                    <div @click="l.inc" style="width:30px;height:100%;display:grid;place-items:center;cursor:pointer;color:#4a5866" v-pseudo:hover="'background:#f2f6fa'">+</div>
                  </div> <input :value="l.note" @input="l.onNote" placeholder="Item note" style="flex:1;min-width:0;height:30px;border:1px solid #dfe6ee;border-radius:7px;padding:0 8px;font-family:'Public Sans',sans-serif;font-size:12px;outline:none" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                </div>
              </template>
              <template v-if="vm.od_cartEmpty">
                <div style="border:1.5px dashed #d3dde8;border-radius:10px;padding:26px 14px;text-align:center;font-size:12.5px;color:#8b98a8">Tap menu items to add them</div>
              </template>
            </div>
            <div style="padding:12px 16px;border-top:1px solid #eaeff5;display:grid;gap:6px;font-size:12.5px">
              <div style="display:flex;align-items:center;gap:8px">
                <div style="flex:1;color:#5d6b7d">Discount</div>
                <div style="display:flex;gap:3px">
                  <template v-for="(d, $index) in vm.od_discs" :key="$index">
                    <div @click="d.onClick" :style="d.style">{{ d.name }}</div>
                  </template>
                </div>
              </div>
              <template v-for="(s, $index) in vm.od_fSums" :key="$index">
                <div :style="s.style">
                  <div style="flex:1">{{ s.k }}</div>
                  <div style="font-family:'IBM Plex Mono',monospace">{{ s.v }}</div>
                </div>
              </template>
              <div @click="vm.od_toggleSend" style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:4px">
                <div :style="vm.od_sendBox">✓</div>
                <div style="font-size:12.5px">Send to kitchen now</div>
              </div>
              <div style="display:flex;gap:7px;margin-top:6px">
                <div @click="vm.od_saveLater" style="flex:1;height:42px;border-radius:9px;border:1px solid #dfe6ee;display:grid;place-items:center;font-size:13px;font-weight:600;color:var(--c-primary-text);cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Pay later</div>
                <div @click="vm.od_savePay" :style="vm.od_payNowStyle">{{ vm.od_payNowLabel }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>

    <template v-if="vm.od_payOpen">
      <div @click="vm.od_closePay" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:40"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:420px;background:#ffffff;border-radius:16px;z-index:41;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px">
        <div style="font-size:17px;font-weight:700">Take payment</div>
        <div style="font-size:12.5px;color:#5d6b7d;margin-top:3px">{{ vm.od_payWhat }}</div>
        <div style="font-family:'IBM Plex Mono',monospace;font-size:30px;font-weight:600;letter-spacing:-0.03em;margin:14px 0">{{ vm.od_payDue }}</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-bottom:12px">
          <template v-for="(m, $index) in vm.od_methods" :key="$index">
            <div @click="m.onClick" :style="m.style">{{ m.name }}</div>
          </template>
        </div>
        <template v-if="vm.od_isCash">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:6px">
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Cash received</label> <input :value="vm.od_tendered" @input="vm.od_onTendered" placeholder="0.00" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
            <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Change</label> <div style="height:36px;border-radius:8px;background:#f6f9fc;display:flex;align-items:center;justify-content:flex-end;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-size:14px;font-weight:600">{{ vm.od_change }}</div></div>
          </div>
          <div style="display:flex;gap:6px;margin-bottom:6px">
            <template v-for="(q, $index) in vm.od_quickCash" :key="$index">
              <div @click="q.onClick" style="height:30px;padding:0 11px;border-radius:7px;border:1px solid #dfe6ee;display:flex;align-items:center;font-family:'IBM Plex Mono',monospace;font-size:12px;cursor:pointer" v-pseudo:hover="'border-color:var(--c-primary);color:var(--c-primary)'">{{ q.label }}</div>
            </template>
          </div>
        </template>
        <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:14px">
          <div @click="vm.od_closePay" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
          <div @click="vm.od_confirmPay" :style="vm.od_confirmStyle">{{ vm.od_confirmLabel }}</div>
        </div>
      </div>
    </template>

    <ToastStack :toasts="vm.od_toasts" :z-index="50" />
  </div>
</template>

<script setup lang="ts">
import ToastStack from '../components/ToastStack.vue';
import { useErpPage } from '../erp/useErpPage';

const vm = useErpPage('orders', 'od_nav');
</script>
