# User Guide

How to use the Material Delivery Performance Monitoring System day to day.

This guide is written for the people who work in the system - purchasing,
warehouse, logistics and management - rather than for developers. For how it is
built, see [docs/01-ARCHITECTURE.md](01-ARCHITECTURE.md).

---

## 1. Signing in

Open the application URL and sign in with the email address and password your
administrator gave you.

There is **no self-service password reset**. This is deliberate: accounts are
created by an administrator, so there is no reset link for anyone to intercept.
If you are locked out, ask your administrator to set a new password. Doing so
signs out every existing session on that account, which is what makes it a safe
recovery step after a suspected compromise.

Your account is refused if it has been deactivated. Repeated failed attempts are
throttled per email address and IP.

### Changing the interface language

The system speaks **English, Bahasa Indonesia, 日本語 and 简体中文**.

- **On the login screen**, the language selector sits at the bottom of the
  sign-in card. You do not need an account to use it - if you cannot read the
  screen, change it first.
- **Once signed in**, the selector is at the bottom of the left sidebar.

Your choice is saved to your account, so it follows you to any browser or device
you sign in from.

**What changes and what does not.** Menus, buttons, column headers, statuses and
error messages all change language. Data that people typed - supplier names,
material codes, PO numbers, problem descriptions, corrective actions - never
does. That is on purpose: those are the records the audit trail is kept against,
and two people must never be looking at two different versions of the same row.

**Printed documents stay in one language** regardless of who exports them, so a
filed report is the same document whoever produced it. Excel exports follow your
interface language.

---

## 2. What your role lets you do

| Role | Typically | Can do |
|---|---|---|
| **SUPER_ADMIN** | System owner | Everything, including users, roles and settings |
| **ADMIN** | IT administrator | Everything |
| **PURCHASING** | Purchasing officer | Purchase orders end to end, suppliers, problems, evaluations, reports |
| **WAREHOUSE** | Warehouse officer | Receiving deliveries, reporting problems, reading POs and master data |
| **LOGISTIC** | Logistics officer | Deliveries and problems, plus evaluation and report access |
| **MANAGEMENT** | Plant manager | Approvals, evaluations, reports, audit log, settings - but not user administration |
| **VIEWER** | Production planner | Read-only across the modules they can reach |
| **SUPPLIER** | External party | A narrow view of their own deliveries and POs |

If a menu item is missing or a button is absent, your role does not carry that
permission. The screen hides what you cannot do rather than showing a button
that will refuse you.

---

## 3. The daily flow

```
Purchase Order  →  Delivery (receiving)  →  Problem (if something is wrong)
                            ↓
                   Dashboard + Supplier performance + Reports
```

### 3.1 Purchase orders

**Purchasing → Purchase Order.**

1. **Create PO** — choose the supplier, the plant, the PO date and the currency.
2. Add **line items**: material, quantity, unit price, scheduled delivery date
   and the receiving warehouse. At least one line is required, and warehouses
   are limited to the plant you chose.
3. **Submit for approval** when the order is complete. It leaves DRAFT.
4. Someone with approval rights **approves** it. Only an approved PO can be
   received against.

A PO moves `DRAFT → SUBMITTED → APPROVED → PARTIAL → COMPLETED`. It can be
**cancelled** at most points, with a reason - never deleted. Business history is
kept, and the audit log records who changed what.

### 3.2 Receiving a delivery

**Warehouse → Delivery → Receive goods.** This is the most important screen in
the system: everything the dashboard reports is calculated from what is entered
here.

1. Pick the approved PO.
2. Enter the **delivery note number**, the **received date**, and optionally the
   driver and vehicle.
3. **Tick only the lines actually received in this shipment.** A partial
   shipment is normal - leave the rest unticked and receive them later against
   the same PO.
4. For each line, enter the quantity received and its condition (Good, Damaged,
   Rejected, Partial).

The system warns you if a quantity exceeds what is still outstanding on the PO.

**Corrections.** If you entered something wrong, use **Correct** on the delivery
rather than creating a second one. Corrections are recorded; deletions are not
possible.

### 3.3 How delivery status is decided

You never set these by hand. The system derives three statuses from what you
entered, and the dashboard aggregates them.

**Punctuality** compares the received date with the scheduled date, by date only
- arriving any time on the scheduled day counts as on time.

| | Meaning |
|---|---|
| ON_TIME | Received on or before the scheduled date |
| LATE | Received after it |

**Quantity** compares received against ordered.

| | Meaning |
|---|---|
| SHORT | Less than ordered |
| FULL | Ordered quantity, within the over-delivery tolerance |
| OVER | Above the tolerance |

The over-delivery tolerance is a **configurable percentage**, not a fixed
number, so a small excess counts as FULL rather than as an over-delivery. It
lives in system settings.

**Overall** combines the two: `ON_TIME_FULL`, `ON_TIME_SHORT`, `LATE_FULL`,
`LATE_SHORT`, or `OVER_DELIVERY`. An over-delivery is reported as such
regardless of punctuality.

### 3.4 Reporting a problem

**From a delivery → Report problem**, or **Problem Analysis → Report Problem**.

Record the category, the severity (Low / Medium / High / Critical), what
happened, the person responsible (PIC) and a target resolution date. The
description must actually explain the event - at least 10 characters.

Then add **corrective actions**: what will be done, by when, and by whom. Each
action moves `OPEN → IN_PROGRESS → DONE`.

**A problem can only be closed once at least one corrective action is Done.**
That rule is enforced, not advisory - it prevents a problem being closed with
nothing recorded against it.

Attachments (photos, delivery notes) can be uploaded to a problem. They are
stored privately and served only to people allowed to see that problem.

---

## 4. Reading the dashboard

**Overview.** The KPI cards show service rate, deliveries, on-time, late, short
and critical materials for the selected period. Use the filter bar to change the
period, plant or supplier; **Reset filters** returns to the default.

- **Service rate trend** — six months. A month with no receipts is *not* drawn
  as 0%, because "no deliveries" is not "0% service".
- **Delivery problem Pareto** — which problem categories account for most of the
  trouble, with a cumulative line. The "vital few" are the categories on the
  left.
- **PO delivery monitoring** — line-level detail: ordered vs received, schedule
  vs actual.

Every threshold behind these figures - the service rate target, the grade
boundaries, the over-delivery tolerance, the critical-material rules - is stored
in the database and editable, not hard-coded.

---

## 5. Supplier performance

**Supplier Performance** ranks suppliers for a period and gives each a grade
(Excellent / Good / Average / Poor) from its service rate and problem record.
Open a supplier for its six-month trend, its problems by category and its
monthly evaluation history.

**Supplier Evaluation** is the formal, approved record.

1. **Calculate monthly evaluation** for a period. This scores every supplier
   that was active in that period on quality, quantity and response.
2. The evaluation starts as **DRAFT**. Review it, and **recalculate** if
   underlying data changed.
3. **Approve** it. An approved evaluation is the record for that period.
4. If it has to change afterwards, **reopen** it - which requires a reason, and
   is recorded.

An evaluation can only be created for a month that has already started.

---

## 6. Critical materials

**Critical Material** lists materials at risk: those flagged critical in master
data, those with a quantity shortage, and those carrying critical problems. Use
it to see where to intervene before a line stops.

A material is flagged critical on its master-data record ("Mark as a critical
material"), and the rules that raise its risk level are configurable.

---

## 7. Reports

**Report** produces five reports - delivery, purchase order, supplier
performance, delivery problem, and critical material - for a chosen period.

Four output formats:

| Format | Use it for |
|---|---|
| **Excel (.xlsx)** | Further analysis. Follows your interface language. |
| **CSV** | Feeding another system |
| **PDF** | Filing and signature |
| **Print** | The browser's own print dialog |

Two limits are deliberate:

- **A report may span at most two years.** Narrow the period, or download it a
  year at a time.
- **Exports are rate limited** to ten a minute, because each one reads every row
  in the period.

If you can see a report but not download it, your role has `report.view` without
`report.export`.

---

## 8. Notifications

The bell in the top bar shows unread notifications: purchase orders awaiting
your approval, and a daily digest of overdue problems that arrives each morning.

Open **Notifications** to read them, mark one as read, or mark all as read.

---

## 9. Administration

Available to SUPER_ADMIN and ADMIN.

**Users.** Create accounts, assign exactly one role, and set department, plant
and employee ID. Every user must hold at least one role.

To remove someone's access, use **Revoke access** rather than deleting the
account - their history stays intact, and the account can be **Restored** later.
An email address stays taken even by a revoked account.

Nobody can raise their own account to SUPER_ADMIN.

**Roles & Permissions.** Shows which modules each role can reach.

**Audit Log.** An append-only record of who changed what and when, filterable by
user, module, action and date. It stores only the attributes that actually
changed. Deleting from it is not possible.

---

## 10. If something goes wrong

| What you see | What it means |
|---|---|
| "This field is required", in your language | Ordinary validation. The message names the field as the form does. |
| A button you expected is missing | Your role does not carry that permission. |
| "Your account can view reports but not download them" | `report.view` without `report.export`. |
| A problem will not close | No corrective action is marked Done yet. |
| Quantity refused as exceeding the remainder | You are receiving more than the PO still has outstanding. Check the PO, or record it as an over-delivery. |
| Signed out unexpectedly | Your password was changed, which ends every session on the account. |
| Cannot sign in at all, no error shown | Ask your administrator whether the site is served over HTTPS. The session cookie is secure-only in production. |

For anything else, your administrator can see the audit log, which records what
actually happened rather than what anyone remembers.
