# User Guide — GLPI Resources Plugin

## 1. Overview

The **Resources** plugin integrates human resources management into GLPI. It covers the full lifecycle of an employee:

- Onboarding declaration (creation wizard)
- Administrative tracking (employer information, clearances, IT needs)
- Management of off-contract periods and imposed leave
- Budget tracking (public sector)
- Job and position management (public sector)
- Departure declaration and tracking
- Inter-entity transfer
- Consolidated user/resource directory
- Bulk resource import

---

## 2. Rights Management

Path: `Administration > Profiles > Human Resources tab`

### 2.1 Available Rights

| Right | Description |
|-------|-------------|
| `plugin_resources` | Main access: view, create, edit, delete resources |
| `plugin_resources_task` | Manage tasks linked to resources |
| `plugin_resources_checklist` | Manage arrival/departure/transfer checklists |
| `plugin_resources_employee` | Access employer information (company, contract, etc.) |
| `plugin_resources_employee_core_form` | Access to the extended employer form |
| `plugin_resources_role` | Manage resource roles |
| `plugin_resources_resting` | Manage off-contract periods |
| `plugin_resources_holiday` | Manage imposed leave |
| `plugin_resources_habilitation` | Manage clearances/accreditations |
| `plugin_resources_employment` | Manage jobs/positions (public sector) |
| `plugin_resources_budget` | Manage resource-linked budgets |
| `plugin_resources_dropdown_public` | Manage public sector dropdowns (grade, stream, etc.) |
| `plugin_resources_import` | Bulk resource import |
| `plugin_resources_open_ticket` | Create a ticket from a resource |
| `plugin_resources_all` | View all resources (not only those you are responsible for) |
| `plugin_resources_leavinginformation` | Access departure information |

> **Note:** Without the `plugin_resources_all` right, a user can only see resources for which they are the designated manager.

---

## 3. General Configuration

Path: `Human Resources > Configuration`

The configuration has four tabs.

### 3.1 Wizard Tab

Configures the behaviour of the resource creation wizard:

- Default manager group
- Default ticket category (for automatic ticket opening)
- Link to a Metademands template (if the Metademands plugin is active)

### 3.2 Arrival / Departure Tab

- Enable automatic notifications on arrival and departure
- Delay before departure notification
- Configure the sales manager
- Enable the resignation form

### 3.3 Other Tab

- Fields to hide in the directory (surname, first name, staff number, phone, etc.)
- Default work quota
- Display options

### 3.4 Metademands Link Tab (optional)

Visible only if the **Metademands** plugin is enabled. Allows associating a Metademands form with the resource creation wizard.

---

## 4. Contract Types

Path: `Configuration > Dropdowns > Contract type`

The contract type controls which steps appear in the creation wizard. Each type can independently enable or disable:

| Option | Description |
|--------|-------------|
| Code | Short identifier for the contract type |
| Employer information | Show the employer tab in the wizard |
| IT needs | Show the hardware/software needs entry step |
| Photo | Show the photo upload step |
| Clearances | Show the clearance entry step |
| Recruitment information | Recruitment source, position start date |
| Second employer list | Enable a second employer selection field |
| Second staff number | Enable a second staff number field |
| Resignation form | Enable the resignation form on departure |
| Documents form | Enable the documents tab in the wizard |

---

## 5. Resource Templates

Path: `Human Resources > Templates`

Templates allow pre-filling fields when creating a resource. A template can contain:

- General information (contract type, location, manager, team)
- Employer information
- Tasks to create automatically
- Checklists to trigger

---

## 6. Rules

### 6.1 Contract Type Rules

Path: `Administration > Rules > Contract type rules`

Based on criteria (contract type, entity, etc.), these rules can:
- **Force values** on certain fields (read-only fields)
- **Hide fields** depending on context
- **Automatically trigger a checklist**

### 6.2 Checklist Rules

Path: `Administration > Rules > Checklist rules`

Automatic assignment of a checklist based on contract type, entity, or other criteria.

---

## 7. Checklists

Path: `Human Resources > Checklists`

A checklist is a list of actions to be performed (unscheduled) associated with a resource event.

### Checklist Types

| Type | Trigger |
|------|---------|
| **Arrival** | When a resource is created |
| **Departure** | When a departure is declared |
| **Transfer** | When an inter-entity transfer occurs |

### Checklist Configuration

Path: `Human Resources > Checklist configuration`

- Checklist name
- Type (arrival, departure, transfer)
- Actions to perform (sub-items)
- Assignment to a group or technician
- Link to a GLPI task

> Checklists are assigned automatically via rules (section 6.2), or manually from the resource record.

---

## 8. Creating a Resource (Wizard)

Path: `Human Resources > Declare a resource`

The wizard guides data entry through several steps depending on the contract type configuration.

### Step 1 — General Information

Available fields:

| Field | Description |
|-------|-------------|
| First name | Employee's first name |
| Surname | Employee's surname |
| Contract type | Determines the subsequent wizard steps |
| Location | Place of work |
| Manager | GLPI user responsible for the resource |
| Sales manager | (optional) Project manager / account manager |
| Service | Service/division |
| Department | Department |
| Team | Team |
| Function | Job title |
| Role | Role in the organisation |
| Arrival date | Contract start date |
| Departure date | Contract end date (optional at creation) |
| Staff number | HR identifier (optional) |
| Second staff number | Depending on contract type configuration |
| Quota | Activity rate (e.g. 100%) |
| Description / Other | Free text field |

### Step 2 — Employer Information (if enabled)

| Field | Description |
|-------|-------------|
| Employer | Employing company |
| Second employer | Depending on configuration |
| Contractual situation | Permanent, fixed-term, temporary, etc. |
| Contract nature | Further details on the nature |
| Grade | (public sector) |
| Stream | (public sector) |
| Speciality | Professional speciality |
| Security awareness | Yes/No |
| Security charter read | Yes/No |

### Step 3 — IT Needs (if enabled)

Entry of required equipment: computer, phone, monitor, peripherals, software, etc.

### Step 4 — Photo (if enabled)

Upload a photo of the resource.

### Step 5 — Clearances (if enabled)

Assignment of clearances/accreditations required for the position.

### Step 6 — Recruitment Information (if enabled)

Recruitment source, position start date, application details.

### Step 7 — Documents (if enabled)

Adding documents to the resource file.

---

## 9. Resource Tabs

Once created, a resource has several tabs:

| Tab | Content |
|-----|---------|
| **Resource** | General information |
| **Employer** | Contractual and HR data (requires `plugin_resources_employee`) |
| **Clearances** | Assigned clearances/accreditations |
| **Tasks** | Scheduled tasks linked to the resource |
| **Checklists** | Arrival/departure actions to complete |
| **Associated items** | Linked equipment (PC, phone, etc.) and associated GLPI user |
| **Employment** | Job/position (public sector, requires `plugin_resources_employment`) |
| **Budget** | Associated budget lines (requires `plugin_resources_budget`) |
| **Off-contract periods** | Maternity leave, long-term sick leave, etc. |
| **Imposed leave** | Public holidays or imposed leave days |
| **Departure** | Departure declaration and information |
| **Tickets/Changes** | Related tickets and changes |
| **Documents** | Attached documents |
| **History** | Change log |
| **Notes** | Internal notes |

---

## 10. Linking to a GLPI User

A GLPI user can be associated with a resource in two ways.

### From the Resource

1. Open the resource record
2. Go to the **Associated items** tab
3. Select the user from the drop-down list

### From the GLPI User

1. Open the user record
2. Go to the **Human Resources** tab
3. Associate with an existing resource or create a new one

---

## 11. Tasks

Path: `Human Resources > Tasks`

Tasks are scheduled actions associated with a resource.

### Task Fields

| Field | Description |
|-------|-------------|
| Name | Task label |
| Type | Task type (configurable dropdown) |
| Resource | Parent resource |
| Manager | Assigned technician |
| Group | Assigned group |
| Start / end date | Scheduling |
| Status | In progress / Completed |
| Comment | Description |

> **Automatic alert:** The `ResourcesTask` automatic action sends a notification for incomplete tasks whose end date has passed.

---

## 12. Off-Contract Periods and Imposed Leave

### Off-Contract Periods

Path: **Off-contract periods** tab on the resource record

Manages contract interruptions (maternity leave, long-term sick leave, etc.) with:
- Start / end date
- Period type
- Detachment location
- Comment

Triggered notifications: `newresting`, `updateresting`, `deleteresting`.

### Imposed Leave

Path: **Imposed leave** tab on the resource record

Manages imposed leave days for the resource, distinct from standard leave.

Triggered notifications: `newholiday`, `updateholiday`, `deleteholiday`.

---

## 13. Clearances / Accreditations

Path: `Configuration > Dropdowns > Clearances`

Clearances are organised in a tree (hierarchy). They represent accreditations or access authorisations assigned to a resource.

### Assignment

From the **Clearances** tab on the resource record:
- Select the clearance
- Set the obtained and expiry dates
- Add a comment

---

## 14. Employment (Public Sector)

Path: **Employment** tab on the resource record (requires `plugin_resources_employment`)

Manages position data specific to the public sector:

| Field | Description |
|-------|-------------|
| Employment status | Active, vacant, etc. |
| Profession category | Category A, B, C… |
| Professional stream | Stream |
| Business line | Sub-stream |
| Grade | Civil servant grade |
| Step | Step within the grade |

Configurable dropdowns: `Configuration > Dropdowns > [Employment]`

---

## 15. Budget (Public Sector)

Path: `Human Resources > Budgets` or **Budget** tab on a resource record

Allows associating budget lines with a resource:

| Field | Description |
|-------|-------------|
| Budget type | Nature of the expenditure |
| Budget volume | Allocated budget |
| Comment | Additional details |

---

## 16. Departure Declaration

### Steps

1. Open the resource record
2. Go to the **Departure** tab
3. Fill in:
   - Departure date
   - Departure reason
   - Informant (who declares the departure)
   - Comment
4. If the resignation form is enabled for this contract type: fill in additional information (resignation reason, notice period, etc.)

### Effects of Departure

- Triggers the **departure checklist**
- Sends the `LeavingResource` notification to configured recipients
- The `Resources` automatic action checks resources whose departure date has passed and sends `AlertLeavingResources`

---

## 17. Inter-Entity Transfer

Path: `Human Resources > [resource] > Actions > Transfer`

Allows moving a resource to another GLPI entity.

Triggered notifications (`transfer`):
- Source entity group
- Target entity group
- Source group manager
- Target group manager

---

## 18. Notifications

Path: `Configuration > Notifications`

### Complete List of Events

| Event | Trigger |
|-------|---------|
| `new` | Resource creation |
| `update` | Resource modification |
| `delete` | Resource deletion |
| `newtask` | Task added |
| `updatetask` | Task modified |
| `deletetask` | Task deleted |
| `LeavingResource` | Departure declaration |
| `AlertLeavingResources` | Resources whose departure date has passed |
| `AlertArrivalChecklists` | Actions to perform on new resources |
| `AlertLeavingChecklists` | Actions to perform on departing resources |
| `AlertExpiredTasks` | Incomplete tasks past their end date |
| `AlertCommercialManager` | List of resources by sales manager |
| `AlertLeavingRessourceManager` | Alert to manager to complete the departure form |
| `report` | Resource creation report |
| `newresting` | Off-contract period added |
| `updateresting` | Off-contract period modified |
| `deleteresting` | Off-contract period deleted |
| `newholiday` | Imposed leave added |
| `updateholiday` | Imposed leave modified |
| `deleteholiday` | Imposed leave deleted |
| `transfer` | Inter-entity transfer |
| `other` | Free notification |

### Available Recipients

| Recipient | Description |
|-----------|-------------|
| Resource manager | `users_id` of the resource |
| Sales manager | `users_id_sales` |
| Requester | Author of the creation/modification |
| Departure informant | User who declared the departure |
| Linked user | GLPI account associated with the resource |
| Task technician | Task manager (task events) |
| Task group | Group assigned to the task (task events) |
| Source entity group | (transfer) |
| Target entity group | (transfer) |
| Source group manager | (transfer) |
| Target group manager | (transfer) |

### Available Template Variables

```
##resource_gender##           Title / salutation
##resource_name##             Surname
##resource_firstname##        First name
##resource_phone##            Phone
##resource_cellphone##        Mobile phone
##resource_locations_id##     Location
##resource_users_id##         Manager
##resource_users_id_sales##   Sales manager
##resource_plugin_resources_departments_id##   Department
##resource_plugin_resources_services_id##      Service
##resource_plugin_resources_functions_id##     Function
##resource_plugin_resources_teams_id##         Team
##resource_date_begin##       Arrival date
##resource_date_end##         Departure date
##resource_comment##          Description
##resource_quota##            Quota
##resource_matricule##        Staff number
##resource_matricule_second## Second staff number
##resource_plugin_resources_ranks_id##             Grade
##resource_plugin_resources_resourcesituations_id## Situation
##resource_plugin_resources_contractnatures_id##    Contract nature
##resource_plugin_resources_resourcespecialities_id## Speciality
##resource_plugin_resources_roles_id##             Role
##resource_sensitize_security##  Security awareness
##resource_read_chart##          Security charter read
```

---

## 19. Automatic Actions

Path: `Configuration > Automatic actions`

| Action | Description |
|--------|-------------|
| `Resources` | Checks resources whose departure date has passed → sends `AlertLeavingResources` |
| `ResourcesChecklist` | Checks unprocessed arrival/departure checklists → sends `AlertArrivalChecklists` and `AlertLeavingChecklists` |
| `ResourcesTask` | Checks incomplete tasks past their end date → sends `AlertExpiredTasks` |

Each automatic action is configurable (enable, mode, frequency) on its dedicated page.

---

## 20. Directory

Path: `Human Resources > Directory`

Consolidated view of GLPI users with their associated resource information.

- Multi-criteria search (surname, first name, service, location, etc.)
- Configurable display (columns hideable via configuration)
- Export available
- Hideable fields configured in `Configuration > Other`: surname, first name, staff number, phone, mobile, location, quota

---

## 21. Resource Import

Path: `Human Resources > Import`

Allows bulk import of resources from a CSV file or via an AD/LDAP source.

### CSV Import

1. Prepare a CSV file with columns matching resource fields
2. Configure the column mapping (`Import column configuration`)
3. Run the import and check the report

### AD/LDAP Import

Path: `Human Resources > LDAP Configuration`

- Configure the AD/LDAP connection
- Map LDAP attributes to resource fields
- One-time import or synchronisation

---

## 22. Resource Card and Badge

### Resource Card

Path: `[resource] > Actions > Print card`

Generates a business card for the resource in PDF format.

### Badge

Path: `[resource] > Actions > Badge`

Generates a printable badge for the resource.

### PDF Export

Path: `[resource] > Actions > Export to PDF`

Generates a complete PDF report of the resource record including all information configured in `Configuration > Report`.

---

## 23. Summary View

Path: `Human Resources > Summary`

Synthetic view of resources by period, allowing visualisation of planned arrivals and departures over a date range.

---

## 24. Configurable Dropdowns

Path: `Configuration > Dropdowns`

| Dropdown | Description |
|----------|-------------|
| Contract type | See section 4 |
| Department | Organisational chart |
| Service | Subdivision of the department |
| Team | Working group |
| Function | Job title |
| Role | Role in the organisation |
| Profession | Occupation |
| Profession category | Grouping of professions |
| Professional stream | (public sector) |
| Business line | (public sector) |
| Grade | (public sector) |
| Rank | (public sector) |
| Contract nature | Further details on the contract type |
| Resource situation | HR status |
| Speciality | Area of expertise |
| Clearance | Accreditation/authorisation (tree structure) |
| Clearance level | Level associated with a clearance |
| Task type | Task category |
| Departure reason | Reason for end of contract |
| Resignation reason | Reason for resignation |
| Recruitment source | Recruitment channel |
| Action profile | Actions to perform based on profile |
| Budget type | Budget category |
| Budget volume | Envelope per budget line |
| Organisational unit | Organisational structure |
| Client | Associated client |

---

## 25. Best Practices

- **Configure contract types** before creating resources so the wizard displays the correct steps
- **Use templates** for recurring profiles (e.g. intern, permanent contractor)
- **Set up checklist rules** to automate arrival/departure action assignment
- **Enable automatic actions** `Resources`, `ResourcesChecklist` and `ResourcesTask` for proactive alerts
- **Configure notifications** `AlertExpiredTasks` and `AlertLeavingResources` to avoid missing critical deadlines
- **Grant the `plugin_resources_all` right** only to HR staff and managers who need to see all employees
- **Hide sensitive fields** in the directory (staff number, phone) if profiles with limited rights have access to it
