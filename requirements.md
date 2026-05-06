# Requirements Document

## Introduction

The Job Portal Web Application is a platform that connects job seekers (students) with employers. Job seekers can register, build profiles, upload resumes, search and apply for jobs, and track their application status. Employers can post and manage job listings, review applicants, and shortlist or reject candidates. An admin role oversees the platform. The system is built on Spring Boot with Thymeleaf, Spring Data JPA (MySQL/H2), and Spring Security for role-based access control.

## Glossary

- **System**: The Job Portal Web Application as a whole.
- **Job_Seeker**: A registered user with the STUDENT role who searches and applies for jobs.
- **Employer**: A registered user with the EMPLOYER role who posts and manages job listings.
- **Admin**: A privileged user with the ADMIN role who manages the platform.
- **Profile**: A Job_Seeker's personal information record including name, contact details, skills, and education.
- **Resume**: A file (PDF or DOCX) uploaded by a Job_Seeker and stored on the server.
- **Job_Listing**: A record created by an Employer containing a job title, description, required skills, location, experience level, and salary range.
- **Application**: A record linking a Job_Seeker to a Job_Listing, with a status of PENDING, SHORTLISTED, or REJECTED.
- **Notification**: An in-app or email message sent to a Job_Seeker when their Application status changes.
- **Dashboard**: A role-specific summary view showing relevant statistics and recent activity.
- **Auth_Service**: The Spring Security component responsible for authentication and authorization.
- **Profile_Service**: The component responsible for managing Job_Seeker profiles and resume uploads.
- **Job_Service**: The component responsible for creating, updating, deleting, and querying Job_Listings.
- **Application_Service**: The component responsible for managing Applications and status transitions.
- **Notification_Service**: The component responsible for sending email and in-app Notifications.
- **File_Store**: The server-side storage location for uploaded Resume files.

---

## Requirements

### Requirement 1: User Registration and Authentication

**User Story:** As a visitor, I want to register an account and log in, so that I can access role-specific features of the portal.

#### Acceptance Criteria

1. WHEN a visitor submits a registration form with a unique email address, full name, password of at least 8 characters, and a role of STUDENT or EMPLOYER, THE Auth_Service SHALL create a new user account with the submitted role and store the password as a bcrypt hash.
2. IF a visitor submits a registration form with an email address that already exists in the system, THEN THE Auth_Service SHALL return a validation error message indicating the email is already registered.
3. IF a visitor submits a registration form with a password shorter than 8 characters, THEN THE Auth_Service SHALL return a validation error message specifying the minimum password length.
4. WHEN a registered user submits valid credentials (email and password), THE Auth_Service SHALL authenticate the user and redirect them to their role-specific Dashboard.
5. IF a user submits invalid credentials, THEN THE Auth_Service SHALL return an authentication error message and SHALL NOT grant access to any protected resource.
6. WHEN an authenticated user requests to log out, THE Auth_Service SHALL invalidate the user's session and redirect them to the login page.
7. WHILE a user is not authenticated, THE System SHALL restrict access to all protected routes and redirect the user to the login page.

---

### Requirement 2: Role-Based Access Control

**User Story:** As a system administrator, I want role-based access enforced on all routes, so that users can only access features appropriate to their role.

#### Acceptance Criteria

1. WHILE a user is authenticated with the STUDENT role, THE Auth_Service SHALL permit access only to Job_Seeker-designated routes and SHALL deny access to Employer and Admin routes.
2. WHILE a user is authenticated with the EMPLOYER role, THE Auth_Service SHALL permit access only to Employer-designated routes and SHALL deny access to Job_Seeker profile routes and Admin routes.
3. WHILE a user is authenticated with the ADMIN role, THE Auth_Service SHALL permit access to all routes in the System.
4. IF an authenticated user attempts to access a route outside their permitted role, THEN THE Auth_Service SHALL return an HTTP 403 response and display an access-denied page.

---

### Requirement 3: Job Seeker Profile Management

**User Story:** As a Job_Seeker, I want to create and maintain my profile, so that employers can learn about my background when I apply for jobs.

#### Acceptance Criteria

1. WHEN a newly registered Job_Seeker accesses their profile page for the first time, THE Profile_Service SHALL display an empty profile form with fields for full name, contact number, location, skills, education, and work experience.
2. WHEN a Job_Seeker submits a profile form with all required fields (full name, contact number, location), THE Profile_Service SHALL save the profile data and display a success confirmation.
3. IF a Job_Seeker submits a profile form with a missing required field, THEN THE Profile_Service SHALL return a field-level validation error and SHALL NOT save the incomplete profile.
4. WHEN a Job_Seeker submits an updated profile form, THE Profile_Service SHALL overwrite the existing profile data with the new values and display a success confirmation.

---

### Requirement 4: Resume Upload

**User Story:** As a Job_Seeker, I want to upload my resume, so that employers can review my qualifications when I apply.

#### Acceptance Criteria

1. WHEN a Job_Seeker uploads a file with a MIME type of application/pdf or application/vnd.openxmlformats-officedocument.wordprocessingml.document and a size not exceeding 5 MB, THE Profile_Service SHALL store the file in the File_Store, record the file path in the Job_Seeker's Profile, and display a success confirmation.
2. IF a Job_Seeker uploads a file with a MIME type other than PDF or DOCX, THEN THE Profile_Service SHALL reject the upload and return an error message specifying the accepted file types.
3. IF a Job_Seeker uploads a file exceeding 5 MB, THEN THE Profile_Service SHALL reject the upload and return an error message specifying the maximum allowed file size.
4. WHEN a Job_Seeker uploads a new resume file, THE Profile_Service SHALL replace the previously stored resume file in the File_Store with the new file.
5. WHEN an Employer views an Application, THE Profile_Service SHALL provide a download link to the applicant's Resume file stored in the File_Store.

---

### Requirement 5: Job Listing Management (Employer)

**User Story:** As an Employer, I want to post, edit, and delete job listings, so that I can attract suitable candidates.

#### Acceptance Criteria

1. WHEN an Employer submits a job creation form with a title, description of at least 50 characters, required skills, location, experience level in years, and salary range, THE Job_Service SHALL create a new Job_Listing associated with the Employer's account and display a success confirmation.
2. IF an Employer submits a job creation form with a missing required field (title, description, location, or experience level), THEN THE Job_Service SHALL return a field-level validation error and SHALL NOT create the Job_Listing.
3. WHEN an Employer submits an edited job form for a Job_Listing they own, THE Job_Service SHALL update the Job_Listing with the new values and display a success confirmation.
4. IF an Employer attempts to edit a Job_Listing they do not own, THEN THE Job_Service SHALL return an HTTP 403 response.
5. WHEN an Employer confirms deletion of a Job_Listing they own, THE Job_Service SHALL delete the Job_Listing and all associated Applications and redirect the Employer to their job management page.
6. IF an Employer attempts to delete a Job_Listing they do not own, THEN THE Job_Service SHALL return an HTTP 403 response.
7. THE Job_Service SHALL associate each Job_Listing with exactly one Employer account (One-to-Many: Employer → Job_Listings).

---

### Requirement 6: Job Search and Filtering (Job Seeker)

**User Story:** As a Job_Seeker, I want to search and filter job listings, so that I can find positions that match my skills and preferences.

#### Acceptance Criteria

1. WHEN a Job_Seeker submits a search query, THE Job_Service SHALL return all Job_Listings whose title or description contains the query string (case-insensitive).
2. WHEN a Job_Seeker applies a category filter, THE Job_Service SHALL return only Job_Listings matching the selected category.
3. WHEN a Job_Seeker applies a location filter, THE Job_Service SHALL return only Job_Listings matching the specified location (case-insensitive).
4. WHEN a Job_Seeker applies an experience level filter specifying a maximum number of years, THE Job_Service SHALL return only Job_Listings whose required experience level is less than or equal to the specified value.
5. WHEN a Job_Seeker applies multiple filters simultaneously, THE Job_Service SHALL return only Job_Listings that satisfy all applied filters.
6. WHEN no filters are applied, THE Job_Service SHALL return all active Job_Listings sorted by creation date in descending order.
7. WHEN a Job_Seeker views a Job_Listing detail page, THE Job_Service SHALL display the title, description, required skills, location, experience level, salary range, and Employer name.

---

### Requirement 7: Job Application (Job Seeker)

**User Story:** As a Job_Seeker, I want to apply for jobs and track my application status, so that I can manage my job search effectively.

#### Acceptance Criteria

1. WHEN a Job_Seeker submits an application for a Job_Listing, THE Application_Service SHALL create a new Application record linking the Job_Seeker to the Job_Listing with an initial status of PENDING.
2. IF a Job_Seeker attempts to submit a second application for a Job_Listing they have already applied to, THEN THE Application_Service SHALL reject the request and return an error message indicating a duplicate application.
3. IF a Job_Seeker attempts to apply for a Job_Listing without a Resume on file, THEN THE Application_Service SHALL reject the request and return an error message instructing the Job_Seeker to upload a resume first.
4. WHEN a Job_Seeker views their applications page, THE Application_Service SHALL display all of their Applications with the Job_Listing title, Employer name, application date, and current status.
5. THE Application_Service SHALL associate each Application with exactly one Job_Listing and exactly one Job_Seeker (Many-to-One: Application → Job_Listing, Application → Job_Seeker).

---

### Requirement 8: Applicant Review and Shortlisting (Employer)

**User Story:** As an Employer, I want to view and manage applicants for my job listings, so that I can identify and shortlist the best candidates.

#### Acceptance Criteria

1. WHEN an Employer views the applicants page for a Job_Listing they own, THE Application_Service SHALL display all Applications for that Job_Listing including the applicant's name, profile summary, resume download link, application date, and current status.
2. WHEN an Employer sets an Application status to SHORTLISTED, THE Application_Service SHALL update the Application status to SHORTLISTED and trigger the Notification_Service to notify the Job_Seeker.
3. WHEN an Employer sets an Application status to REJECTED, THE Application_Service SHALL update the Application status to REJECTED and trigger the Notification_Service to notify the Job_Seeker.
4. IF an Employer attempts to update the status of an Application for a Job_Listing they do not own, THEN THE Application_Service SHALL return an HTTP 403 response.
5. WHEN an Employer views their Dashboard, THE Application_Service SHALL display the total number of Applications received across all of the Employer's Job_Listings and the number of Applications with SHORTLISTED status.

---

### Requirement 9: Notifications

**User Story:** As a Job_Seeker, I want to receive notifications when my application status changes, so that I can respond promptly to opportunities.

#### Acceptance Criteria

1. WHEN the Application_Service updates an Application status to SHORTLISTED or REJECTED, THE Notification_Service SHALL send an email to the Job_Seeker's registered email address containing the Job_Listing title and the new status.
2. WHEN the Application_Service updates an Application status to SHORTLISTED or REJECTED, THE Notification_Service SHALL create an in-app Notification record for the Job_Seeker.
3. WHEN a Job_Seeker views their notifications page, THE Notification_Service SHALL display all unread Notifications for that Job_Seeker sorted by creation date in descending order.
4. WHEN a Job_Seeker marks a Notification as read, THE Notification_Service SHALL update the Notification record's read status to true.
5. IF the Notification_Service fails to deliver an email, THEN THE Notification_Service SHALL log the failure with the recipient address and Job_Listing title and SHALL NOT prevent the Application status update from completing.

---

### Requirement 10: Dashboard Analytics

**User Story:** As an Employer or Admin, I want a dashboard with summary statistics, so that I can monitor portal activity at a glance.

#### Acceptance Criteria

1. WHEN an Employer views their Dashboard, THE Job_Service SHALL display the total number of active Job_Listings posted by that Employer.
2. WHEN an Admin views the Admin Dashboard, THE Job_Service SHALL display the total number of Job_Listings across all Employers.
3. WHEN an Admin views the Admin Dashboard, THE Auth_Service SHALL display the total number of registered Job_Seeker accounts and the total number of registered Employer accounts.
4. WHEN an Admin views the Admin Dashboard, THE Application_Service SHALL display the total number of Applications across all Job_Listings and the number of Applications with SHORTLISTED status.

---

### Requirement 11: Input Validation and Error Handling

**User Story:** As a developer, I want consistent input validation and error handling across all API endpoints, so that the system behaves predictably and securely.

#### Acceptance Criteria

1. THE System SHALL validate all form inputs using Bean Validation annotations (@NotNull, @Size, @Email) before processing any request.
2. IF a request fails Bean Validation, THEN THE System SHALL return a structured error response containing the field name and a descriptive error message for each violated constraint.
3. IF an unhandled exception occurs during request processing, THEN THE System SHALL log the exception with a stack trace and return an HTTP 500 response with a generic error message that does not expose internal implementation details.
4. THE System SHALL handle all exceptions through a centralized @ControllerAdvice component.
5. IF a requested resource (Job_Listing, Application, Profile) is not found, THEN THE System SHALL return an HTTP 404 response with a descriptive error message.
