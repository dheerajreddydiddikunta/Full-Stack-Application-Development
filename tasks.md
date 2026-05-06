# Implementation Plan: Job Portal Web Application

## Overview

Implement a multi-role job portal using Spring Boot 3, Thymeleaf, Spring Data JPA (MySQL/H2), and Spring Security. The plan follows a bottom-up approach: project scaffolding → data layer → security → services → controllers → templates → tests. Each task builds on the previous so there is no orphaned code.

## Tasks

- [ ] 1. Project setup and configuration
  - Generate a Spring Boot 3 project via Spring Initializr with dependencies: Spring Web, Thymeleaf, Spring Security, Spring Data JPA, MySQL Driver, H2, Validation, Spring Mail, Lombok, Thymeleaf Extras Spring Security 6, Thymeleaf Layout Dialect
  - Add jqwik `1.8.4` to `pom.xml` in `<scope>test</scope>`
  - Create `src/main/resources/application.yml` with datasource (MySQL prod / H2 test profiles), file-store config (`app.file-store.base-dir`, `app.file-store.max-size-bytes`, `app.file-store.allowed-types`), and Spring Mail SMTP settings (host, port, STARTTLS, credentials via env vars `MAIL_USERNAME` / `MAIL_PASSWORD`)
  - Create `src/test/resources/application-test.yml` pointing to H2 in-memory datasource
  - Create the top-level package structure: `model`, `dto`, `repository`, `service`, `service.impl`, `controller`, `security`, `exception`, `config`
  - _Requirements: 1.1, 4.1, 9.1_

- [ ] 2. Domain model — entities and enums
  - [ ] 2.1 Create `Role` enum (`STUDENT`, `EMPLOYER`, `ADMIN`), `ApplicationStatus` enum (`PENDING`, `SHORTLISTED`, `REJECTED`), and `ListingStatus` enum (`ACTIVE`, `CLOSED`) in the `model` package
    - _Requirements: 1.1, 7.1_

  - [ ] 2.2 Implement `User` entity with all fields, JPA annotations, and bidirectional relationships as specified in the design (`@OneToOne` Profile, `@OneToMany` JobListings / Applications / Notifications, `createdAt`)
    - _Requirements: 1.1, 5.7, 7.5_

  - [ ] 2.3 Implement `Profile` entity with all fields (`fullName`, `contactNumber`, `location`, `skills`, `education`, `workExperience`, `resumePath`, `resumeFileName`, `updatedAt`) and `@OneToOne` back-reference to `User`
    - _Requirements: 3.1, 3.2, 4.1_

  - [ ] 2.4 Implement `JobListing` entity with all fields, `@ManyToOne` employer, `@OneToMany` applications with `orphanRemoval = true`, Bean Validation annotations (`@NotBlank`, `@Size(min=50)`, `@Min(0)`)
    - _Requirements: 5.1, 5.7_

  - [ ] 2.5 Implement `Application` entity with `@ManyToOne` jobSeeker and jobListing, `@Enumerated` status, `@UniqueConstraint` on `(job_seeker_id, job_listing_id)`, `appliedAt`, `updatedAt`
    - _Requirements: 7.1, 7.5_

  - [ ] 2.6 Implement `Notification` entity with `@ManyToOne` recipient and application, `message`, `read = false`, `createdAt`
    - _Requirements: 9.2_

- [ ] 3. DTO classes
  - [ ] 3.1 Create `RegistrationDto` (email `@Email @NotBlank`, fullName `@NotBlank`, password `@Size(min=8)`, role `@NotNull`) and `ProfileDto` (fullName `@NotBlank`, contactNumber `@NotBlank`, location `@NotBlank`, skills, education, workExperience)
    - _Requirements: 1.1, 1.3, 3.2, 3.3_

  - [ ] 3.2 Create `JobListingDto` (title `@NotBlank`, description `@Size(min=50)` `@NotBlank`, requiredSkills, location `@NotBlank`, category `@NotBlank`, experienceLevelYears `@Min(0)`, salaryRange) and `JobSearchCriteria` (keyword, category, location, maxExperience)
    - _Requirements: 5.1, 5.2, 6.1–6.5_

  - [ ] 3.3 Create `ApplicationStatusDto` (status `@NotNull`)
    - _Requirements: 8.2, 8.3_

- [ ] 4. JPA repositories
  - [ ] 4.1 Create `UserRepository extends JpaRepository<User, Long>` with `Optional<User> findByEmail(String email)` and `long countByRole(Role role)`
    - _Requirements: 1.1, 10.3_

  - [ ] 4.2 Create `ProfileRepository extends JpaRepository<Profile, Long>` with `Optional<Profile> findByUserId(Long userId)`
    - _Requirements: 3.1, 4.1_

  - [ ] 4.3 Create `JobListingRepository extends JpaRepository<JobListing, Long>, JpaSpecificationExecutor<JobListing>` with `List<JobListing> findByEmployerId(Long employerId)` and `long countByEmployerId(Long employerId)`
    - _Requirements: 5.1, 6.6, 10.1_

  - [ ] 4.4 Create `ApplicationRepository extends JpaRepository<Application, Long>` with `Optional<Application> findByJobSeekerIdAndJobListingId(Long seekerId, Long listingId)`, `List<Application> findByJobSeekerId(Long seekerId)`, `List<Application> findByJobListingId(Long listingId)`, `long countByJobListingEmployerId(Long employerId)`, and `long countByJobListingEmployerIdAndStatus(Long employerId, ApplicationStatus status)`
    - _Requirements: 7.2, 7.4, 8.1, 8.5_

  - [ ] 4.5 Create `NotificationRepository extends JpaRepository<Notification, Long>` with `List<Notification> findByRecipientIdAndReadFalseOrderByCreatedAtDesc(Long userId)` and `Optional<Notification> findByIdAndRecipientId(Long id, Long userId)`
    - _Requirements: 9.3, 9.4_

- [ ] 5. Custom exception classes
  - Create `ResourceNotFoundException`, `DuplicateApplicationException`, `ResumeRequiredException`, `FileStorageException`, `InvalidFileTypeException` (extends `FileStorageException`), `FileSizeLimitException` (extends `FileStorageException`), and `NotificationDeliveryException` in the `exception` package — all extending `RuntimeException` with message constructors
  - _Requirements: 11.3, 11.4, 11.5_

- [ ] 6. Security configuration
  - [ ] 6.1 Implement `CustomUserDetailsService implements UserDetailsService` in the `security` package — loads `User` by email from `UserRepository`, maps `Role` to a Spring Security `GrantedAuthority` (`ROLE_STUDENT`, `ROLE_EMPLOYER`, `ROLE_ADMIN`)
    - _Requirements: 1.4, 1.5_

  - [ ] 6.2 Implement `RoleBasedSuccessHandler implements AuthenticationSuccessHandler` — redirects `STUDENT` → `/dashboard/student`, `EMPLOYER` → `/dashboard/employer`, `ADMIN` → `/dashboard/admin`
    - _Requirements: 1.4_

  - [ ] 6.3 Implement `SecurityConfig` (`@Configuration @EnableWebSecurity @EnableMethodSecurity`) with the full `SecurityFilterChain` bean: permit `/auth/**`, `/css/**`, `/js/**`, `/images/**`, `/jobs`, `/jobs/{id}`; role-restrict all other routes as per the design; configure `formLogin`, `logout`, `exceptionHandling` (accessDeniedPage `/error/403`); register `BCryptPasswordEncoder` bean
    - _Requirements: 1.4, 1.6, 1.7, 2.1, 2.2, 2.3, 2.4_

- [ ] 7. Auth service and controller
  - [ ] 7.1 Implement `AuthServiceImpl implements AuthService` — `register()` checks for duplicate email (throws `DuplicateApplicationException` variant or a `RegistrationException`), hashes password with `BCryptPasswordEncoder`, persists `User`; `loadUserByUsername()` delegates to `CustomUserDetailsService`
    - _Requirements: 1.1, 1.2_

  - [ ]* 7.2 Write property tests for `AuthServiceImpl` (jqwik)
    - **Property 1: Password is stored as a bcrypt hash, never plaintext** — generate valid `RegistrationDto` instances, call `register()`, assert `passwordHash` starts with `$2a$` and does not equal the plaintext password
    - **Property 2: Duplicate email registration is always rejected** — register once, attempt second registration with same email, assert exception thrown and only one `User` record exists
    - **Property 3: Invalid credentials never grant access** — for any registered user, attempt `loadUserByUsername` with a wrong password via `BCryptPasswordEncoder.matches()`, assert it returns false
    - **Validates: Requirements 1.1, 1.2, 1.5**

  - [ ] 7.3 Implement `AuthController` with `GET /auth/register`, `POST /auth/register` (validates `RegistrationDto`, handles duplicate email flash error), `GET /auth/login`, `GET /auth/logout` (handled by Spring Security)
    - _Requirements: 1.1, 1.2, 1.3, 1.6_

- [ ] 8. Profile service and controller
  - [ ] 8.1 Implement `ProfileServiceImpl implements ProfileService` — `getOrCreateProfile()` returns existing or new empty `Profile`; `saveProfile()` maps `ProfileDto` to entity and saves; `storeResume()` validates MIME type and size (throws `InvalidFileTypeException` / `FileSizeLimitException`), deletes previous file if present, writes new file to `{base-dir}/resumes/{userId}/{UUID}-{originalName}`, updates `resumePath` and `resumeFileName`; `loadResumeAsResource()` resolves path and returns `UrlResource`
    - _Requirements: 3.1, 3.2, 3.4, 4.1, 4.2, 4.3, 4.4, 4.5_

  - [ ]* 8.2 Write property tests for `ProfileServiceImpl` (jqwik)
    - **Property 6: Profile data is persisted and retrievable (round-trip)** — generate valid `ProfileDto`, call `saveProfile()`, retrieve from repo, assert all fields match
    - **Property 7: Invalid profile submissions are rejected without persisting data** — generate `ProfileDto` with at least one blank required field, assert Bean Validation rejects it and no record is created/modified
    - **Property 8: Valid resume uploads are stored and path is recorded** — generate valid PDF/DOCX mock files ≤ 5 MB, call `storeResume()`, assert file exists on disk and `resumePath` is non-null/non-empty
    - **Property 9: Invalid resume uploads are always rejected** — generate files with wrong MIME type or size > 5 MB, assert exception thrown and `resumePath` unchanged
    - **Property 10: Re-uploading a resume replaces the previous file** — upload first file, upload second file, assert only new file exists in File_Store and `resumePath` points to new file
    - **Validates: Requirements 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.4**

  - [ ] 8.3 Implement `ProfileController` with `GET /profile` (view), `GET /profile/edit`, `POST /profile/edit` (validates `ProfileDto`, handles `BindingResult`), `POST /profile/resume` (multipart upload), `GET /profile/resume/{userId}` (download with `Content-Disposition: attachment`)
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.5_

- [ ] 9. Job service and controller
  - [ ] 9.1 Implement `JobSpecification` class providing static factory methods that return `Specification<JobListing>` predicates for keyword (title/description LIKE, case-insensitive), category (exact match), location (LIKE, case-insensitive), and maxExperience (≤ value); compose them with `Specification.where().and()` in `JobServiceImpl.search()`
    - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

  - [ ] 9.2 Implement `JobServiceImpl implements JobService` — `createListing()` associates employer and persists; `updateListing()` and `deleteListing()` perform ownership check (throw `AccessDeniedException` on mismatch); `search()` builds `Specification` from `JobSearchCriteria` and delegates to `JobListingRepository`; `getById()` throws `ResourceNotFoundException` if absent
    - _Requirements: 5.1, 5.3, 5.4, 5.5, 5.6, 5.7, 6.1–6.6_

  - [ ]* 9.3 Write property tests for `JobServiceImpl` (jqwik)
    - **Property 11: Valid job listings are persisted with correct employer association** — generate valid `JobListingDto`, call `createListing()`, assert persisted entity's `employer.id` matches and fields match DTO
    - **Property 12: Invalid job listing submissions are rejected without persisting data** — generate `JobListingDto` with missing/invalid required field, assert Bean Validation rejects and no record created
    - **Property 13: Ownership is enforced for all job listing mutations** — create listing for employer A, attempt update/delete as employer B, assert `AccessDeniedException` and listing unchanged
    - **Property 14: Deleting a job listing cascades to all associated applications** — create listing with N applications, call `deleteListing()`, assert listing and all N applications removed
    - **Property 15: Multi-filter job search returns only listings satisfying all active filters** — generate varied listings and filter combinations, assert result set is exactly the intersection satisfying all filters
    - **Property 16: Unfiltered job search results are sorted by creation date descending** — generate listings with distinct `createdAt`, call `search()` with no filters, assert descending order
    - **Validates: Requirements 5.1, 5.2, 5.4, 5.5, 5.6, 5.7, 6.1–6.6**

  - [ ] 9.4 Implement `JobController` with `GET /jobs` (search + filter form, paginated results), `GET /jobs/{id}` (detail), `GET /jobs/new`, `POST /jobs/new` (EMPLOYER only, validates `JobListingDto`), `GET /jobs/{id}/edit`, `POST /jobs/{id}/edit` (EMPLOYER only), `POST /jobs/{id}/delete` (EMPLOYER only)
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.1–6.7_

- [ ] 10. Application service and controller
  - [ ] 10.1 Implement `ApplicationServiceImpl implements ApplicationService` — `apply()` checks resume presence (throws `ResumeRequiredException`) and duplicate (throws `DuplicateApplicationException`), creates `Application` with `status = PENDING`; `updateStatus()` checks listing ownership (throws `AccessDeniedException`), updates status, calls `notificationService.notify()`; `getBySeeker()` and `getByListing()` delegate to repository; `getStatsForEmployer()` returns counts via repository aggregation methods
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 10.2 Write property tests for `ApplicationServiceImpl` (jqwik)
    - **Property 17: New applications are created with PENDING status** — generate seeker with resume and listing, call `apply()`, assert `Application.status == PENDING` and correct seeker/listing links
    - **Property 18: Duplicate applications are always rejected** — apply once, apply again for same pair, assert exception and only one record exists
    - **Property 19: Applications without a resume on file are always rejected** — seeker with null/empty `resumePath`, call `apply()`, assert `ResumeRequiredException` and no record created
    - **Property 20: Application status transitions trigger notifications** — update status to SHORTLISTED or REJECTED, assert `notificationService.notify()` called exactly once, in-app record persisted with `read = false`, `JavaMailSender` invoked with correct email and subject
    - **Validates: Requirements 7.1, 7.2, 7.3, 7.5, 8.2, 8.3, 9.1, 9.2**

  - [ ] 10.3 Implement `ApplicationController` with `POST /applications/apply/{listingId}` (STUDENT), `GET /applications/my` (STUDENT — list with listing title, employer name, date, status), `GET /applications/{listingId}/applicants` (EMPLOYER — list with applicant name, profile summary, resume link, date, status), `POST /applications/{id}/status` (EMPLOYER — validates `ApplicationStatusDto`)
    - _Requirements: 7.1, 7.2, 7.3, 7.4, 8.1, 8.2, 8.3, 8.4_

- [ ] 11. Notification service and controller
  - [ ] 11.1 Implement `NotificationServiceImpl implements NotificationService` — `notify()` always persists `Notification` record first, then attempts email via `JavaMailSender` inside try/catch (logs `MailException`, does not rethrow); `getUnreadForUser()` delegates to repository ordered query; `markAsRead()` finds notification by id+userId, sets `read = true`, saves
    - _Requirements: 9.1, 9.2, 9.3, 9.4, 9.5_

  - [ ]* 11.2 Write property tests for `NotificationServiceImpl` (jqwik)
    - **Property 21: Email delivery failure does not prevent notification persistence** — mock `JavaMailSender.send()` to throw `MailException`, call `notify()`, assert in-app `Notification` persisted and no exception propagates
    - **Property 22: Unread notifications are returned sorted by creation date descending** — create N unread notifications at varied timestamps plus some read ones, call `getUnreadForUser()`, assert exactly N results in descending `createdAt` order with no read notifications
    - **Property 23: Marking a notification as read sets its read flag to true** — call `markAsRead()` on an unread notification, assert `read = true` and notification absent from subsequent `getUnreadForUser()` result
    - **Validates: Requirements 9.1, 9.2, 9.3, 9.4, 9.5**

  - [ ] 11.3 Implement `NotificationController` with `GET /notifications` (STUDENT — list unread notifications sorted by date) and `POST /notifications/{id}/read` (STUDENT — mark as read, redirect back)
    - _Requirements: 9.3, 9.4_

- [ ] 12. Dashboard controllers
  - [ ] 12.1 Implement `DashboardController` with three handler methods:
    - `GET /dashboard/student` (STUDENT) — adds unread notification count and recent applications to model
    - `GET /dashboard/employer` (EMPLOYER) — adds total active listings count (`Job_Service`) and application stats (`Application_Service`) to model
    - `GET /dashboard/admin` (ADMIN) — adds total listings, total STUDENT count, total EMPLOYER count, total applications, and SHORTLISTED count to model
    - _Requirements: 8.5, 10.1, 10.2, 10.3, 10.4_

  - [ ]* 12.2 Write property test for dashboard aggregation (jqwik)
    - **Property 24: Dashboard aggregation counts are accurate** — create employer with N active listings and M total applications (K shortlisted), call `getStatsForEmployer()` and admin stat queries, assert counts exactly match N, M, K
    - **Validates: Requirements 8.5, 10.1, 10.2, 10.3, 10.4**

- [ ] 13. Global exception handler
  - Implement `GlobalExceptionHandler` (`@ControllerAdvice`) with `@ExceptionHandler` methods for `ResourceNotFoundException` (→ `error/404`), `AccessDeniedException` (→ `error/403`), `DuplicateApplicationException` (→ redirect `/jobs` with flash error), `ResumeRequiredException` (→ redirect with flash error), `FileStorageException` (→ redirect `/profile/edit` with flash error), and `Exception` (→ `error/500` with generic message, log stack trace)
  - _Requirements: 11.3, 11.4, 11.5_

- [ ] 14. Checkpoint — core backend complete
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 15. Security access control tests (MockMvc)
  - [ ] 15.1 Implement `SecurityAccessControlTest` using `@WebMvcTest` + `@WithMockUser` — parameterize over all protected routes and role combinations
    - **Property 4: Unauthenticated requests to protected routes are always redirected** — for each protected route, send unauthenticated request, assert HTTP 302 redirect to `/auth/login`
    - **Property 5: Role-based access control is enforced for every role/route combination** — for each (role, route) pair outside the role's permitted set, assert HTTP 403; for ADMIN + any route, assert never HTTP 403
    - **Validates: Requirements 1.7, 2.1, 2.2, 2.3, 2.4**

  - [ ]* 15.2 Write property tests for Bean Validation error responses (jqwik + MockMvc)
    - **Property 25: Bean Validation errors include field-level details** — generate form submissions with one or more violated constraints, assert response contains at least one entry per violation with field name and non-empty message
    - **Validates: Requirements 11.2**

  - [ ]* 15.3 Write property tests for missing resource responses (jqwik + MockMvc)
    - **Property 26: Missing resources return HTTP 404** — generate random non-existent IDs for JobListing, Application, and Profile endpoints, assert HTTP 404 with descriptive message not exposing internals
    - **Validates: Requirements 11.5**

- [ ] 16. Thymeleaf templates — layout and auth
  - [ ] 16.1 Create `src/main/resources/templates/layout/base.html` (Thymeleaf Layout Dialect base template with `<head>`, navbar fragment include, `layout:fragment="content"` slot, CSS/JS links) and `layout/navbar.html` (role-aware nav using `sec:authorize` for STUDENT/EMPLOYER/ADMIN links)
    - _Requirements: 2.1, 2.2, 2.3_

  - [ ] 16.2 Create `auth/login.html` (form binding to Spring Security `/auth/login`, error flash display, link to register) and `auth/register.html` (form binding to `RegistrationDto`, `th:errors` inline validation, role selector)
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [ ] 17. Thymeleaf templates — profile
  - Create `profile/view.html` (display all profile fields, resume filename with download link if present, edit button) and `profile/edit.html` (form bound to `ProfileDto` with `th:field` and `th:errors` for required fields, resume upload `<input type="file">` with accepted MIME types, success/error flash messages)
  - _Requirements: 3.1, 3.2, 3.3, 3.4, 4.1, 4.2, 4.3, 4.5_

- [ ] 18. Thymeleaf templates — jobs
  - Create `jobs/list.html` (search input, category/location/experience filter form, paginated results table with title, employer, location, experience, apply button for STUDENT, edit/delete buttons for owning EMPLOYER), `jobs/detail.html` (full listing details, apply button for STUDENT, resume download link for EMPLOYER), and `jobs/form.html` (shared create/edit form bound to `JobListingDto` with `th:errors`)
  - _Requirements: 5.1, 5.2, 5.3, 5.5, 6.1–6.7_

- [ ] 19. Thymeleaf templates — applications and notifications
  - Create `applications/my-applications.html` (table of STUDENT's applications: listing title, employer name, date, status badge) and `applications/applicants.html` (EMPLOYER view: applicant name, profile summary, resume download link, date, status, SHORTLIST/REJECT action buttons)
  - Create `notifications/list.html` (list of unread notifications sorted by date, mark-as-read button per notification)
  - _Requirements: 7.4, 8.1, 8.2, 8.3, 9.3, 9.4_

- [ ] 20. Thymeleaf templates — dashboards and error pages
  - Create `dashboard/student.html` (unread notification count, recent applications summary), `dashboard/employer.html` (active listings count, total/shortlisted application counts, link to manage listings), `dashboard/admin.html` (total listings, student count, employer count, total/shortlisted application counts)
  - Create `error/403.html` (access denied message), `error/404.html` (resource not found message), `error/500.html` (generic error message — no stack trace)
  - _Requirements: 8.5, 10.1, 10.2, 10.3, 10.4, 11.3, 11.5_

- [ ] 21. Static assets
  - Create `src/main/resources/static/css/main.css` with base styles (typography, form layout, table styles, status badge colours for PENDING/SHORTLISTED/REJECTED, flash message styles, responsive nav)
  - Create `src/main/resources/static/js/main.js` with minimal JS (confirm dialog for delete actions, auto-dismiss flash messages)
  - _Requirements: 11.1_

- [ ] 22. Integration tests
  - [ ] 22.1 Implement `RegistrationLoginFlowIT` (`@SpringBootTest` + H2 + `MockMvc`) — full flow: register STUDENT → login → assert redirect to `/dashboard/student`; register EMPLOYER → login → assert redirect to `/dashboard/employer`
    - _Requirements: 1.1, 1.4_

  - [ ] 22.2 Implement `JobApplicationFlowIT` — post listing as EMPLOYER → apply as STUDENT (with resume) → EMPLOYER updates status to SHORTLISTED → assert `Notification` record created with `read = false` and `JavaMailSender` invoked
    - _Requirements: 5.1, 7.1, 8.2, 9.1, 9.2_

  - [ ] 22.3 Implement `ResumeUploadDownloadIT` — upload valid PDF as STUDENT → assert file written to temp File_Store → download via `GET /profile/resume/{userId}` → assert `Content-Disposition: attachment` header and file bytes match
    - _Requirements: 4.1, 4.5_

  - [ ] 22.4 Implement `AdminDashboardIT` — seed multiple users, listings, and applications → call admin dashboard endpoint → assert all stat counts are accurate
    - _Requirements: 10.2, 10.3, 10.4_

- [ ] 23. Final checkpoint — all tests pass
  - Ensure all unit, property-based, and integration tests pass. Ask the user if questions arise.

## Notes

- Sub-tasks marked with `*` are optional and can be skipped for a faster MVP build.
- Each task references specific requirements for full traceability.
- Property-based tests use jqwik 1.8.4 with a minimum of 100 iterations per property (`@Property(tries = 100)`).
- All 26 correctness properties from the design document are covered across tasks 7.2, 8.2, 9.3, 10.2, 11.2, 12.2, 15.1, 15.2, and 15.3.
- Integration tests use H2 in-memory database with `@Transactional` rollback and a `@TempDir` for file storage.
- Service-layer tests mock all repositories and external dependencies (JavaMailSender, filesystem) via Mockito.
