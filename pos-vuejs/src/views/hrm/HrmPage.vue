<template>
  <div>
    <div style="padding:18px 24px 0;background:#ffffff;border-bottom:1px solid #dfe6ee">
      <!-- The old screens (Employees ... Tips) take their header from the HRM logic; the new screens have their own. -->
      <template v-if="!page">
      <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>HRM</span><span style="color:#c2cedb">/</span><span @click="vm.hr_crumbClick" :style="vm.hr_crumbStyle">{{ vm.hr_crumb }}</span> <template v-if="vm.hr_isEmp"><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ vm.hr_e.name }}</span></template></div>
      <div style="display:flex;align-items:flex-start;gap:20px;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ vm.hr_title }}</h2>
          <div style="font-size:12.5px;color:#5d6b7d">{{ vm.hr_sub }}</div>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center">
          <template v-for="(a, $index) in vm.hr_headActions" :key="$index">
            <div @click="a.onClick" :style="a.style">{{ a.label }}</div>
          </template>
        </div>
      </div>
      </template>
      <template v-else>
        <div style="display:flex;align-items:center;gap:7px;font-size:11.5px;color:#7d8b9c;margin-bottom:8px"><span>HRM</span><span style="color:#c2cedb">/</span><span style="color:#0f1c2b;font-weight:500">{{ page.title }}</span></div>
        <h2 style="margin:0 0 3px;font-size:22px;font-weight:700;letter-spacing:-0.018em">{{ page.title }}</h2>
        <div style="font-size:12.5px;color:#5d6b7d">{{ page.sub }}</div>
      </template>
      <div style="display:flex;gap:2px;margin-top:14px;flex-wrap:wrap">
        <template v-for="(t, $index) in vm.hr_tabs" :key="$index">
          <div @click="openOld(t)" :style="page ? tabStyle(false) : t.style">{{ t.name }}<span v-if="!page" :style="t.countStyle">{{ t.count }}</span></div>
        </template>
      </div>
      <!-- The new HRM screens -->
      <div style="display:flex;gap:4px 14px;flex-wrap:wrap;align-items:center;padding:8px 0 10px;border-top:1px solid #eef2f6">
        <div v-for="g in groups" :key="g.name" style="display:flex;gap:2px;align-items:center;flex-wrap:wrap">
          <span style="font-family:'IBM Plex Mono',monospace;font-size:9.5px;letter-spacing:0.07em;text-transform:uppercase;color:#9aa7b6;margin-right:4px">{{ g.name }}</span>
          <RouterLink v-for="l in g.links" :key="l.name" :to="{ name: l.name }" :style="linkStyle(route.name === l.name || route.meta.parent === l.name)">{{ l.title }}</RouterLink>
        </div>
      </div>
    </div>

    <div style="padding:18px 24px 32px;display:grid;gap:14px">
      <RouterView />
    </div>

    <!-- overlays -->
    <template v-if="vm.hr_formOpen">
      <div @click="vm.hr_closeAll" style="position:absolute;inset:0;background:rgba(9,22,35,.42);z-index:30"></div>
      <div style="position:absolute;top:0;right:0;bottom:0;width:500px;background:#ffffff;z-index:31;display:flex;flex-direction:column;box-shadow:-20px 0 50px -24px rgba(9,22,35,.6)">
        <div style="padding:16px 22px;border-bottom:1px solid #eaeff5;display:flex;align-items:center;gap:12px">
          <div>
            <div style="font-size:16px;font-weight:700">{{ vm.hr_fTitle }}</div>
            <div style="font-size:12px;color:#5d6b7d;margin-top:2px">One form for add and edit</div>
          </div>
          <div @click="vm.hr_closeAll" style="margin-left:auto;width:32px;height:32px;border-radius:8px;border:1px solid #dfe6ee;display:grid;place-items:center;cursor:pointer;color:#5d6b7d;font-size:15px" v-pseudo:hover="'background:#f2f6fa'">×</div>
        </div>
        <div style="display:flex;gap:2px;padding:0 18px;border-bottom:1px solid #eaeff5">
          <template v-for="(t, $index) in vm.hr_fTabs" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}<span :style="t.errStyle">!</span></div>
          </template>
        </div>
        <div style="flex:1;overflow-y:auto;padding:18px 22px;display:grid;gap:14px;align-content:start">
          <template v-if="vm.hr_fOnPersonal">
            <div style="display:grid;gap:14px">
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">First name <span style="color:#d93a3a">*</span></label><input :value="vm.hr_f.first" @input="vm.hr_on.first" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Last name <span style="color:#d93a3a">*</span></label><input :value="vm.hr_f.last" @input="vm.hr_on.last" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1.3fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Mobile <span style="color:#d93a3a">*</span></label><input :value="vm.hr_f.phone" @input="vm.hr_on.phone" placeholder="04xx xxx xxx" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Email</label><input :value="vm.hr_f.email" @input="vm.hr_on.email" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1.3fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Date of birth</label><input :value="vm.hr_f.dob" @input="vm.hr_on.dob" placeholder="DD/MM/YYYY" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Emergency contact</label><input :value="vm.hr_f.emerg" @input="vm.hr_on.emerg" placeholder="Name and number" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </div>
            </div>
          </template>
          <template v-if="vm.hr_fOnJob">
            <div style="display:grid;gap:14px">
              <div style="display:grid;grid-template-columns:1.3fr 1fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Designation <span style="color:#d93a3a">*</span></label><input :value="vm.hr_f.role" @input="vm.hr_on.role" placeholder="e.g. Butcher" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Department</label><select :value="vm.hr_f.dept" @change="vm.hr_on.dept" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
                  <template v-for="(o, $index) in vm.hr_deptOpts" :key="$index">
                    <option :value="o.v">{{ o.v }}</option>
                  </template>
                </select></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Location</label><select :value="vm.hr_f.loc" @change="vm.hr_on.loc" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
                  <template v-for="(o, $index) in vm.hr_locOpts" :key="$index">
                    <option :value="o.v">{{ o.v }}</option>
                  </template>
                </select></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Start date</label><input type="date" :value="vm.hr_f.start" @input="vm.hr_on.start" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </div>
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Employment type</label> <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
                <template v-for="(t, $index) in vm.hr_fTypes" :key="$index">
                  <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
                </template>
              </div></div>
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">POS PIN</label><input :value="vm.hr_f.pin" @input="vm.hr_on.pin" :placeholder="vm.hr_f.hasPin ? 'Set. Type 4 digits to change it' : '4 digits'" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px;width:120px" v-pseudo:focus="'border-color:var(--c-primary)'" /><div style="font-size:11px;color:#8b98a8;margin-top:4px">Used to clock in and to sign in at the register</div></div>
            </div>
          </template>
          <template v-if="vm.hr_fOnPay">
            <div style="display:grid;gap:14px">
              <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Pay basis</label> <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
                <template v-for="(t, $index) in vm.hr_fBasis" :key="$index">
                  <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
                </template>
              </div></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">{{ vm.hr_fRateLabel }} <span style="color:#d93a3a">*</span></label><input :value="vm.hr_f.rate" @input="vm.hr_on.rate" placeholder="0.00" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" /><div style="font-size:11px;color:#8b98a8;margin-top:4px">{{ vm.hr_fRateHint }}</div></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Ordinary hours / fortnight</label><input :value="vm.hr_f.hours" @input="vm.hr_on.hours" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px;text-align:right" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Tax file number</label><input :value="vm.hr_f.tfn" @input="vm.hr_on.tfn" placeholder="9 digits" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
                <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Super fund</label><input :value="vm.hr_f.superFund" @input="vm.hr_on.superFund" placeholder="e.g. AustralianSuper" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
              </div>
              <div style="background:#f6f9fc;border-radius:10px;padding:11px 13px;font-size:12px;color:#4a5866;line-height:1.55">{{ vm.hr_fPayPreview }}</div>
            </div>
          </template>
          <template v-if="vm.hr_fHasErr">
            <div style="background:#fdeaea;border:1px solid #f3cfcf;border-radius:10px;padding:10px 13px;display:grid;gap:4px">
              <template v-for="(e, $index) in vm.hr_fErrs" :key="$index">
                <div style="font-size:12px;color:#b02020">× {{ e.msg }}</div>
              </template>
            </div>
          </template>
        </div>
        <div style="padding:14px 22px;border-top:1px solid #eaeff5;display:flex;gap:8px;justify-content:flex-end">
          <div @click="vm.hr_closeAll" style="height:34px;padding:0 13px;border-radius:8px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:12.5px;font-weight:500;color:#4a5866;cursor:pointer;background:#ffffff" v-pseudo:hover="'background:#f2f6fa'">Cancel</div>
          <div @click="vm.hr_saveEmp" style="height:34px;padding:0 15px;border-radius:8px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:12.5px;font-weight:600;cursor:pointer" v-pseudo:hover="'background:var(--c-primary-hover)'">{{ vm.hr_fSave }}</div>
        </div>
      </div>
    </template>

    <template v-if="vm.hr_leaveFormOpen">
      <div @click="vm.hr_closeAll" style="position:absolute;inset:0;background:rgba(9,22,35,.48);z-index:30"></div>
      <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:440px;background:#ffffff;border-radius:16px;z-index:31;box-shadow:0 30px 80px -24px rgba(9,22,35,.65);padding:22px;display:grid;gap:13px">
        <div style="font-size:17px;font-weight:700">New leave request</div>
        <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Employee</label><select :value="vm.hr_lf.emp" @change="vm.hr_onLf.emp" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;cursor:pointer;padding:0 8px">
          <template v-for="(o, $index) in vm.hr_empOpts" :key="$index">
            <option :value="o.id">{{ o.name }}</option>
          </template>
        </select></div>
        <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Type</label> <div style="display:flex;gap:3px;background:#eef2f6;padding:3px;border-radius:9px">
          <template v-for="(t, $index) in vm.hr_lfTypes" :key="$index">
            <div @click="t.onClick" :style="t.style">{{ t.name }}</div>
          </template>
        </div></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 0.7fr;gap:10px">
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">From</label><input type="date" :value="vm.hr_lf.from" @input="vm.hr_onLf.from" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">To</label><input type="date" :value="vm.hr_lf.to" @input="vm.hr_onLf.to" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff;font-family:'IBM Plex Mono',monospace;font-size:12.5px" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
          <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Days</label><div style="height:36px;border-radius:8px;background:#f6f9fc;display:flex;align-items:center;justify-content:flex-end;padding:0 11px;font-family:'IBM Plex Mono',monospace;font-weight:600">{{ vm.hr_lfDays }}</div></div>
        </div>
        <div :style="vm.hr_lfBalStyle">{{ vm.hr_lfBal }}</div>
        <div><label style="display:block;font-size:12px;font-weight:600;margin-bottom:5px">Note</label><input :value="vm.hr_lf.note" @input="vm.hr_onLf.note" placeholder="Optional" style="width:100%;height:36px;border:1px solid #dfe6ee;border-radius:8px;padding:0 11px;font-family:'Public Sans',sans-serif;font-size:13px;outline:none;background:#ffffff" v-pseudo:focus="'border-color:var(--c-primary)'" /></div>
        <div style="display:flex;gap:9px;justify-content:flex-end;margin-top:4px">
          <div @click="vm.hr_closeAll" style="height:40px;padding:0 16px;border-radius:9px;border:1px solid #dfe6ee;display:flex;align-items:center;font-size:13px;font-weight:600;color:#4a5866;cursor:pointer">Cancel</div>
          <div @click="vm.hr_saveLeave" style="height:40px;padding:0 16px;border-radius:9px;background:var(--c-primary);color:#ffffff;display:flex;align-items:center;font-size:13px;font-weight:700;cursor:pointer">Submit request</div>
        </div>
      </div>
    </template>

    <ToastStack :toasts="vm.hr_toasts" :z-index="50" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ToastStack from '../../components/ToastStack.vue';
import { useErpPage } from '../../erp/useErpPage';
import { can } from '../../api/auth.js';

const vm = useErpPage('hrm', 'hr_nav');
const route = useRoute();
const router = useRouter();

// The new HRM screens: route name -> title, the line under it, and the permission needed to see the link.
const PAGES: Record<string, { title: string, sub: string, need?: string }> = {
  'hrm-dashboard': { title: 'HRM dashboard', sub: 'Staff, today’s attendance, overtime and what is waiting for approval.' },
  'hrm-daily': { title: 'Daily attendance', sub: 'Everyone’s day: punches, breaks, late and absent. Fix a day by hand.' },
  'hrm-approvals': { title: 'Approvals', sub: 'Leave, overtime, attendance fixes, deductions and payroll waiting for you.' },
  'hrm-files': { title: 'Staff files', sub: 'Full employee records: job, probation, documents, education, bank and history.' },
  'hrm-file': { title: 'Staff file', sub: 'One employee’s full record.' },
  'hrm-leave-balances': { title: 'Leave balances', sub: 'Balances per leave type and the leave calendar.' },
  'hrm-overtime': { title: 'Overtime', sub: 'Overtime from attendance and requests, and the monthly summary.' },
  'hrm-reports': { title: 'Attendance reports', sub: 'Daily, monthly, late, absent, overtime and more, with filters and export.' },
  'hrm-salary': { title: 'Salary', sub: 'Salary structures, bonuses, advances and loans.', need: 'hrm.payroll' },
  'hrm-deductions': { title: 'Deductions', sub: 'Deductions worked out from attendance by the policies, or added by hand.', need: 'hrm.payroll' },
  'hrm-monthly-payroll': { title: 'Monthly payroll', sub: 'The month’s pay from salaries, overtime and deductions: approve, pay, payslips.', need: 'hrm.payroll' },
  'hrm-shifts': { title: 'Shifts', sub: 'Shift times, and who works which shift from when.' },
  'hrm-holidays': { title: 'Holidays', sub: 'Public and company holidays. Work on a holiday counts as overtime.' },
  'hrm-policies': { title: 'Policies', sub: 'Leave, overtime and deduction rules.' },
  'hrm-setup': { title: 'HRM setup', sub: 'Departments, designations, approval workflows and HRM settings.' },
};
const GROUPS = [
  { name: 'Work', links: ['hrm-dashboard', 'hrm-daily', 'hrm-approvals', 'hrm-files', 'hrm-leave-balances', 'hrm-overtime', 'hrm-reports'] },
  { name: 'Pay', links: ['hrm-salary', 'hrm-deductions', 'hrm-monthly-payroll'] },
  { name: 'Setup', links: ['hrm-shifts', 'hrm-holidays', 'hrm-policies', 'hrm-setup'] },
];
const groups = computed(() => GROUPS.map(g => ({
  name: g.name,
  links: g.links.filter(n => !PAGES[n].need || can(PAGES[n].need as string)).map(n => ({ name: n, title: PAGES[n].title })),
})).filter(g => g.links.length));

const page = computed(() => PAGES[route.name as string] || null);

// The old tabs keep their state in the HRM logic; from a new screen, go back to the tab's own page.
const OLD_ROUTES: Record<string, string> = { Employees: 'hrm', Attendance: 'hrm-attendance', Leave: 'hrm-leave', Payroll: 'hrm-payroll', Tips: 'hrm-tips' };
function openOld(t: any) {
  t.onClick();
  if (page.value) router.push({ name: OLD_ROUTES[t.name] || 'hrm' });
}

const tabStyle = (on: boolean) => 'padding:9px 14px;font-size:13px;cursor:pointer;border-bottom:2px solid ' + (on ? 'var(--c-primary);color:var(--c-primary);font-weight:600;' : 'transparent;color:#5d6b7d;font-weight:500;');
const linkStyle = (on: boolean) => 'padding:4px 9px;border-radius:7px;font-size:12px;text-decoration:none;' + (on ? 'background:var(--c-primary-soft);color:var(--c-primary-text);font-weight:600;' : 'color:#4a5866;font-weight:500;');
</script>
