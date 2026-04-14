# PM Training Request Management Feature - Documentation

## Overview
The PM Training Request Management feature has been successfully added to your LMS system. This new feature allows users to create, manage, and track PM (Program Management) training requests with attendee management.

## Files Created/Modified

### 1. Database Files
- **pm_training_migration.sql** - SQL migration script containing two new database tables
- **run_migration.php** - PHP script to execute the migration (already run)

### 2. PHP Files  
- **public/pm_training_management.php** - Main management page with all functionality

### 3. Modified Files
- **inc/sidebar.php** - Added navigation link to PM Training Request management

## Database Tables Created

### pm_training_requests
Stores the training request information:
- `id` - Primary key
- `title` - Training title
- `venue` - Training venue/location
- `date_start` - Start date
- `date_end` - End date
- `hospital_order_no` - Hospital order number
- `amount` - Amount (PHP currency)
- `late_filing` - Late filing indicator (boolean)
- `remarks` - Additional remarks
- `requester_id` - Reference to user who created the request
- `ptr_file` - PTR (Post Training Report) attachment
- `attendance_file` - Attendance list attachment
- `status` - Request status (pending/approved/rejected)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

### pm_training_attendance
Tracks attendee information for each training:
- `id` - Primary key
- `pm_training_request_id` - Reference to pm_training_requests
- `user_id` - Reference to user attending
- `attended` - Attendance status (boolean)
- `created_at` - Creation timestamp
- `updated_at` - Last update timestamp

## Features Implemented

### 1. Add New Training Request Modal
The "New Training Request" button opens a modal with the following fields:
- **Title** (required) - Name of the training
- **Venue** (required) - Training location dropdown with ability to add new venues
- **Date Start** (required) - Training start date
- **Date End** (required) - Training end date
- **Hospital Order No.** - Optional order number
- **Amount** - Optional amount in PHP currency
- **Late Filing** - Checkbox to mark late filing
- **Remarks** - Additional comments
- **Attendees** (required) - Searchable list of all users with multi-select checkboxes

### 2. Training Requests Table
Displays all PM training requests with columns:
- ID
- Title
- Venue
- Start Date
- End Date
- Requester Name
- Hospital Order No.
- Amount
- Remarks (truncated with hover tooltip)
- Status (pending/approved/rejected with color coding)
- Action Buttons (Edit, Attendance, Delete)

### 3. Edit Training Request Modal
Opens when clicking the Edit button with features:
- **Pre-filled Information** - All original data is loaded
- **Read-only Dates** - Start and end dates are disabled (grayed out)
- **File Uploads**
  - PTR File (Post Training Report)
  - Attendance File (can be Excel, PDF, etc.)
- **Approval Option** - Admin/Superadmin can approve requests
- **Attendee Management** - Update attendee list
- **Searchable Attendees** - Filter attendees by name

### 4. Attendance Check Modal
Opens when clicking the Attendance button with features:
- **Searchable Attendee List** - Find specific attendees
- **Checkbox Selection** - Mark attendees as attended or not
- **Save Functionality** - Updates attendance status in database

### 5. Search and Filter
- **Search** - Find requests by title, venue, order number, or remarks
- **Status Filter** - Filter by pending or approved status
- **Reset** - Clear all filters

### 6. Permission Controls
- **Regular Users** - Can only see and edit their own requests
- **Admin/Superadmin** - Can see all requests and approve them

## How to Use

### Creating a New Training Request
1. Click **"New Training Request"** button
2. Fill in the required fields (Title, Venue, Dates, Attendees)
3. Select attendees from the searchable list
4. Click **"Submit Request"**
5. Request will be created with pending status

### Editing a Training Request
1. Click the **Edit** button in the Actions column
2. Update any information except dates
3. Upload PTR and/or Attendance files if needed
4. Update attendee list
5. Click **"Update Request"**

### Checking Attendance
1. Click the **Attendance** button (people icon) in the Actions column
2. Search for specific attendees if needed
3. Check boxes next to names who attended
4. Click **"Save Attendance"**

### Viewing/Filtering Requests
1. Use the **Search** field to find specific requests
2. Use the **Status** dropdown to filter by pending/approved
3. Click **Reset** to clear filters

### Deleting a Request
1. Click the **Delete** button (trash icon) in the Actions column
2. Confirm deletion in the dialog

## Access
The PM Training Request Management page is accessible from:
- **Sidebar Menu** - Look for "PM Training Request" link
- **Direct URL** - `/lms/public/pm_training_management.php`

## Permissions
- **All Users** - Can create PM training requests
- **Requesters** - Can edit/delete their own requests
- **Admins/Superadmins** - Can view and manage all requests, approve them

## File Attachments
Files are stored in: `/uploads/pm_training/`

Supported formats:
- PDF
- JPEG, JPG, PNG
- DOC, DOCX
- XLSX, CSV

## Status Flow
1. **Pending** - Initial status when request is created
2. **Approved** - Admin/Superadmin can approve the request
3. **Rejected** - Can be used to reject requests

## Navigation Link
The feature is accessible from the sidebar menu under "PM Training Request" with a graduation cap icon (fa-graduation-cap).

## Database Indexes
- Unique constraint on (pm_training_request_id, user_id) in pm_training_attendance
- Foreign key relationships ensure data integrity

## Notes
- All date comparisons are validated to ensure end date is not before start date
- Attendee information is automatically populated from the users table
- Search functionality supports partial matching
- Toast notifications provide user feedback for all operations
- Responsive design works on desktop and mobile devices

## Next Steps (Optional Enhancements)
- Export attendance reports to Excel
- Email notifications for request status changes
- Bulk operations for attendance
- Calendar view for training schedules
- Attendance QR code scanning
- Certificate generation upon completion
