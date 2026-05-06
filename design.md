# Design Document: Job Portal Web Application

## Overview

The Job Portal Web Application is a multi-role web platform built on **Spring Boot 3**, **Thymeleaf**, **Spring Data JPA**, and **Spring Security**. It connects three user roles — Job Seekers (STUDENT), Employers, and Admins — through a server-rendered MVC architecture backed by a relational database (MySQL in production, H2 for testing).

The system is decomposed into five logical service components:

| Service | Responsibility |
|---|---|
| **Auth_Service** | Registration, login, logout, session management, password hashing |
| **Profile_Service** | Job Seeker profile CRUD, resume file upload/download |
| **Job_Service** | Job listing creation, editing, deletion, search, and filtering |
| **Application_Service** | Application submission, status transitions, duplicate detection |
| **Notification_Service** | In-app notification records, email dispatch via JavaMailSender |

All HTML is rendered server-side via Thymeleaf templates. Spring Security enforces role-based access on every route. File uploads are stored on the local filesystem in a configurable `File_Store` directory.

---

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        Browser (Client)                         │
└──────────────────────────┬──────────────────────────────────────┘
                           │ HTTP/HTTPS
┌──────────────────────────▼──────────────────────────────────────┐
│                  Spring Boot Application                        │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────────────┐  │
│  │  Spring MVC  │  │   Thymeleaf  │  │   Spring Security    │  │
│  │ Controllers  │  │  Templates   │  │  (Auth + RBAC)       │  │
│  └──────┬───────┘  └──────────────┘  └──────────────────────┘  │
│         │                                                       │
│  ┌──────▼───────────────────────────────────────────────────┐  │
│  │                    Service Layer                          │  │
│  │  Auth_Service │ Profile_Service │ Job_Service            │  │
│  │  Application_Service │ Notification_Service              │  │
│  └──────┬───────────────────────────────────────────────────┘  │
│         │                                                       │
│  ┌──────▼───────────────────────────────────────────────────┐  │
│  │              Spring Data JPA (Repository Layer)           │  │
│  └──────┬───────────────────────────────────────────────────┘  │
│         │                                                       │
│  ┌──────▼──────────┐   ┌──────────────────────────────────┐   │
│  │  MySQL / H2 DB  │   │  File_Store (local filesystem)   │   │
│  └─────────────────┘   └──────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

### Request Flow

1. Browser sends HTTP request.
2. Spring Security filter chain intercepts — checks authentication and role authorization.
3. If authorized, the request reaches the appropriate `@Controller`.
4. The controller delegates business logic to the relevant service(s).
5. Services interact with JPA repositories for persistence.
6. The controller populates a Thymeleaf `Model` and returns a template name.
7. Thymeleaf renders the HTML response.

### Technology Stack

| Layer | Technology |
|---|---|
| Language | Java 17 |
| Framework | Spring Boot 3.x |
| Web / MVC | Spring MVC |
| Templating | Thymeleaf 3 + Thymeleaf Security Extras |
| Security | Spring Security 6 |
| Persistence | Spring Data JPA + Hibernate |
| Database | MySQL 8 (prod), H2 (test) |
| Validation | Jakarta Bean Validation (Hibernate Validator) |
| Email | Spring Mail (JavaMailSender) |
| File Storage | Local filesystem (MultipartFile → File_Store) |
| Build | Maven |
| Testing | JUnit 5, Mockito, jqwik (property-based) |

---

## Components and Interfaces

### Auth_Service

```java
public interface AuthService {
    void register(RegistrationDto dto);          // creates User, hashes password
    UserDetails loadUserByUsername(String email); // implements UserDetailsService
}
```

- Implements `UserDetailsService` for Spring Security integration.
- Uses `BCryptPasswordEncoder` for password hashing.
- On successful registration, creates a `User` entity with the chosen role.

### Profile_Service

```java
public interface ProfileService {
    Profile getOrCreateProfile(Long userId);
    Profile saveProfile(Long userId, ProfileDto dto);
    String storeResume(Long userId, MultipartFile file);  // returns stored file path
    Resource loadResumeAsResource(Long userId);
}
```

- Validates MIME type (PDF / DOCX) and file size (≤ 5 MB) before writing to `File_Store`.
- Stores the relative file path in the `Profile.resumePath` field.
- Replaces the previous resume file on re-upload.

### Job_Service

```java
public interface JobService {
    JobListing createListing(Long employerId, JobListingDto dto);
    JobListing updateListing(Long employerId, Long listingId, JobListingDto dto);
    void deleteListing(Long employerId, Long listingId);
    Page<JobListing> search(JobSearchCriteria criteria, Pageable pageable);
    JobListing getById(Long listingId);
}
```

- Ownership checks: throws `AccessDeniedException` (→ HTTP 403) when `employerId` does not match `JobListing.employer.id`.
- `JobSearchCriteria` encapsulates keyword, category, location, and maxExperience filters.
- Uses JPA Specifications (or a custom JPQL query) for dynamic multi-filter search.

### Application_Service

```java
public interface ApplicationService {
    Application apply(Long seekerId, Long listingId);
    Application updateStatus(Long employerId, Long applicationId, ApplicationStatus status);
    List<Application> getBySeeker(Long seekerId);
    List<Application> getByListing(Long employerId, Long listingId);
    ApplicationStats getStatsForEmployer(Long employerId);
}
```

- Duplicate check: queries for an existing `Application` with the same `(seekerId, listingId)` pair.
- Resume check: verifies `Profile.resumePath` is non-null before creating an application.
- After a status update to SHORTLISTED or REJECTED, calls `NotificationService.notify(application)`.

### Notification_Service

```java
public interface NotificationService {
    void notify(Application application);                    // creates in-app record + sends email
    List<Notification> getUnreadForUser(Long userId);
    void markAsRead(Long userId, Long notificationId);
}
```

- Email sending is wrapped in a try/catch; failures are logged but do not propagate.
- In-app `Notification` records are always persisted regardless of email outcome.

### Controller Layer

| Controller | Base Path | Roles |
|---|---|---|
| `AuthController` | `/auth/**` | PUBLIC |
| `ProfileController` | `/profile/**` | STUDENT |
| `JobController` | `/jobs/**` | PUBLIC (read), STUDENT (apply), EMPLOYER (manage) |
| `ApplicationController` | `/applications/**` | STUDENT, EMPLOYER |
| `NotificationController` | `/notifications/**` | STUDENT |
| `AdminController` | `/admin/**` | ADMIN |
| `DashboardController` | `/dashboard/**` | STUDENT, EMPLOYER, ADMIN |

---

## Data Models

### Entity Relationship Diagram

```
User (1) ──────────────── (0..1) Profile
  │                                  │
  │ (1)                              │ resumePath
  │                                  │
  ├──── (0..*) JobListing ──── (0..*) Application ──── (0..*) Notification
  │              │                       │
  │              │ employer              │ jobSeeker
  │              │                       │
  └──────────────┘                       └──── User (STUDENT)
```

### User Entity

```java
@Entity
@Table(name = "users")
public class User {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(unique = true, nullable = false)
    @Email private String email;

    @Column(nullable = false)
    private String passwordHash;

    @Column(nullable = false)
    private String fullName;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false)
    private Role role;  // STUDENT | EMPLOYER | ADMIN

    @OneToOne(mappedBy = "user", cascade = CascadeType.ALL, fetch = FetchType.LAZY)
    private Profile profile;

    @OneToMany(mappedBy = "employer", cascade = CascadeType.ALL)
    private List<JobListing> jobListings;

    @OneToMany(mappedBy = "jobSeeker", cascade = CascadeType.ALL)
    private List<Application> applications;

    @OneToMany(mappedBy = "recipient", cascade = CascadeType.ALL)
    private List<Notification> notifications;

    private LocalDateTime createdAt;
}
```

### Profile Entity

```java
@Entity
@Table(name = "profiles")
public class Profile {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @OneToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "user_id", nullable = false, unique = true)
    private User user;

    @NotBlank private String fullName;
    @NotBlank private String contactNumber;
    @NotBlank private String location;

    @Column(columnDefinition = "TEXT")
    private String skills;

    @Column(columnDefinition = "TEXT")
    private String education;

    @Column(columnDefinition = "TEXT")
    private String workExperience;

    private String resumePath;   // relative path within File_Store
    private String resumeFileName; // original filename for display

    private LocalDateTime updatedAt;
}
```

### JobListing Entity

```java
@Entity
@Table(name = "job_listings")
public class JobListing {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "employer_id", nullable = false)
    private User employer;

    @NotBlank private String title;

    @Size(min = 50)
    @Column(columnDefinition = "TEXT", nullable = false)
    private String description;

    @Column(columnDefinition = "TEXT")
    private String requiredSkills;

    @NotBlank private String location;
    @NotBlank private String category;

    @Min(0) private int experienceLevelYears;

    private String salaryRange;

    @Enumerated(EnumType.STRING)
    private ListingStatus status;  // ACTIVE | CLOSED

    private LocalDateTime createdAt;
    private LocalDateTime updatedAt;

    @OneToMany(mappedBy = "jobListing", cascade = CascadeType.ALL, orphanRemoval = true)
    private List<Application> applications;
}
```

### Application Entity

```java
@Entity
@Table(name = "applications",
       uniqueConstraints = @UniqueConstraint(columnNames = {"job_seeker_id", "job_listing_id"}))
public class Application {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "job_seeker_id", nullable = false)
    private User jobSeeker;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "job_listing_id", nullable = false)
    private JobListing jobListing;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false)
    private ApplicationStatus status;  // PENDING | SHORTLISTED | REJECTED

    private LocalDateTime appliedAt;
    private LocalDateTime updatedAt;
}
```

### Notification Entity

```java
@Entity
@Table(name = "notifications")
public class Notification {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "recipient_id", nullable = false)
    private User recipient;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "application_id", nullable = false)
    private Application application;

    @Column(nullable = false)
    private String message;

    @Column(nullable = false)
    private boolean read = false;

    private LocalDateTime createdAt;
}
```

### DTO Classes

| DTO | Fields | Used By |
|---|---|---|
| `RegistrationDto` | email, fullName, password, role | AuthController |
| `ProfileDto` | fullName, contactNumber, location, skills, education, workExperience | ProfileController |
| `JobListingDto` | title, description, requiredSkills, location, category, experienceLevelYears, salaryRange | JobController |
| `JobSearchCriteria` | keyword, category, location, maxExperience | JobController |
| `ApplicationStatusDto` | status | ApplicationController |

---

## Security Configuration

### Spring Security Filter Chain

```java
@Configuration
@EnableWebSecurity
@EnableMethodSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/auth/**", "/css/**", "/js/**", "/images/**").permitAll()
                .requestMatchers("/jobs", "/jobs/{id}").permitAll()
                .requestMatchers("/profile/**").hasRole("STUDENT")
                .requestMatchers("/jobs/new", "/jobs/*/edit", "/jobs/*/delete").hasRole("EMPLOYER")
                .requestMatchers("/applications/*/status").hasRole("EMPLOYER")
                .requestMatchers("/applications/my").hasRole("STUDENT")
                .requestMatchers("/notifications/**").hasRole("STUDENT")
                .requestMatchers("/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )
            .formLogin(form -> form
                .loginPage("/auth/login")
                .loginProcessingUrl("/auth/login")
                .successHandler(roleBasedSuccessHandler())
                .failureUrl("/auth/login?error=true")
                .permitAll()
            )
            .logout(logout -> logout
                .logoutUrl("/auth/logout")
                .logoutSuccessUrl("/auth/login?logout=true")
                .invalidateHttpSession(true)
                .deleteCookies("JSESSIONID")
            )
            .exceptionHandling(ex -> ex
                .accessDeniedPage("/error/403")
            );
        return http.build();
    }

    @Bean
    public PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder();
    }
}
```

### Role-Based Success Handler

After login, users are redirected to their role-specific dashboard:

- `STUDENT` → `/dashboard/student`
- `EMPLOYER` → `/dashboard/employer`
- `ADMIN` → `/dashboard/admin`

### Method-Level Security

Ownership checks in service methods use `@PreAuthorize` or manual checks:

```java
// In JobService
public JobListing updateListing(Long employerId, Long listingId, JobListingDto dto) {
    JobListing listing = jobListingRepository.findById(listingId)
        .orElseThrow(() -> new ResourceNotFoundException("Job listing not found"));
    if (!listing.getEmployer().getId().equals(employerId)) {
        throw new AccessDeniedException("You do not own this listing");
    }
    // ... update fields
}
```

### Thymeleaf Security Dialect

Templates use `sec:authorize` to conditionally render role-specific UI elements:

```html
<div sec:authorize="hasRole('EMPLOYER')">
    <a th:href="@{/jobs/new}">Post a Job</a>
</div>
<div sec:authorize="hasRole('STUDENT')">
    <a th:href="@{/applications/my}">My Applications</a>
</div>
```

---

## File Storage Design

### Configuration

```yaml
# application.yml
app:
  file-store:
    base-dir: ${user.home}/job-portal/uploads
    max-size-bytes: 5242880   # 5 MB
    allowed-types:
      - application/pdf
      - application/vnd.openxmlformats-officedocument.wordprocessingml.document
```

### Storage Strategy

Files are stored under `{base-dir}/resumes/{userId}/` with a UUID-based filename to prevent collisions and path traversal:

```
{base-dir}/
  resumes/
    42/
      a3f1c2d4-resume.pdf
    87/
      b9e0f1a2-resume.docx
```

### Upload Flow

```
MultipartFile (HTTP POST)
    │
    ▼
ProfileController.uploadResume()
    │
    ▼
ProfileService.storeResume(userId, file)
    ├── Validate MIME type (ContentType header + Tika sniffing)
    ├── Validate file size (≤ 5 MB)
    ├── Delete previous file if exists (Profile.resumePath != null)
    ├── Generate path: resumes/{userId}/{UUID}-{originalName}
    ├── Files.copy(file.getInputStream(), targetPath)
    └── Update Profile.resumePath + Profile.resumeFileName
```

### Download Flow

```
GET /profile/resume/{userId}
    │
    ▼
ProfileController.downloadResume()
    │
    ▼
ProfileService.loadResumeAsResource(userId)
    ├── Resolve path from Profile.resumePath
    ├── Return UrlResource
    └── Set Content-Disposition: attachment; filename="{resumeFileName}"
```

---

## Email Notification Design

### Configuration

```yaml
spring:
  mail:
    host: smtp.gmail.com
    port: 587
    username: ${MAIL_USERNAME}
    password: ${MAIL_PASSWORD}
    properties:
      mail.smtp.auth: true
      mail.smtp.starttls.enable: true
```

### NotificationService Implementation

```java
@Service
public class NotificationServiceImpl implements NotificationService {

    private final NotificationRepository notificationRepo;
    private final JavaMailSender mailSender;

    @Override
    public void notify(Application application) {
        // 1. Always persist in-app notification
        Notification notification = new Notification();
        notification.setRecipient(application.getJobSeeker());
        notification.setApplication(application);
        notification.setMessage(buildMessage(application));
        notification.setCreatedAt(LocalDateTime.now());
        notificationRepo.save(notification);

        // 2. Attempt email — failure must not propagate
        try {
            SimpleMailMessage mail = new SimpleMailMessage();
            mail.setTo(application.getJobSeeker().getEmail());
            mail.setSubject("Application Update: " + application.getJobListing().getTitle());
            mail.setText(buildEmailBody(application));
            mailSender.send(mail);
        } catch (MailException e) {
            log.error("Failed to send email to {} for listing {}: {}",
                application.getJobSeeker().getEmail(),
                application.getJobListing().getTitle(),
                e.getMessage());
        }
    }
}
```

### Email Template

```
Subject: Application Update: {Job Title}

Dear {Applicant Name},

Your application for the position of "{Job Title}" at {Employer Name}
has been updated to: {NEW_STATUS}

Log in to the portal to view more details.

Best regards,
Job Portal Team
```

---

## Frontend Structure (Thymeleaf Templates)

### Template Directory Layout

```
src/main/resources/
  templates/
    layout/
      base.html          ← Thymeleaf Layout Dialect base template
      navbar.html        ← Role-aware navigation fragment
    auth/
      login.html
      register.html
    profile/
      view.html
      edit.html
    jobs/
      list.html          ← Search + filter form + results
      detail.html
      form.html          ← Create / Edit (shared)
    applications/
      my-applications.html
      applicants.html    ← Employer view of applicants per listing
    notifications/
      list.html
    dashboard/
      student.html
      employer.html
      admin.html
    error/
      403.html
      404.html
      500.html
  static/
    css/
      main.css
    js/
      main.js
```

### Base Layout (Thymeleaf Layout Dialect)

```html
<!-- layout/base.html -->
<!DOCTYPE html>
<html xmlns:th="http://www.thymeleaf.org"
      xmlns:sec="http://www.thymeleaf.org/extras/spring-security"
      xmlns:layout="http://www.ultraq.net.nz/thymeleaf/layout">
<head>
    <meta charset="UTF-8"/>
    <title layout:title-pattern="$CONTENT_TITLE - Job Portal">Job Portal</title>
    <link rel="stylesheet" th:href="@{/css/main.css}"/>
</head>
<body>
    <nav th:replace="~{layout/navbar :: navbar}"></nav>
    <main layout:fragment="content"></main>
    <script th:src="@{/js/main.js}"></script>
</body>
</html>
```

### Key Template Patterns

- **Flash messages**: Controllers add success/error messages via `RedirectAttributes.addFlashAttribute()`, rendered in the base layout.
- **Form binding**: Thymeleaf `th:object` and `th:field` bind DTO objects to forms; `th:errors` displays Bean Validation errors inline.
- **Pagination**: Job listing search results use Spring's `Page<T>` with Thymeleaf pagination fragment.
- **CSRF**: Spring Security auto-injects CSRF tokens into Thymeleaf forms via `th:action`.

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*


### Property 1: Password is stored as a bcrypt hash, never plaintext

*For any* valid registration DTO (unique email, full name, password ≥ 8 characters, valid role), after calling `AuthService.register()`, the stored `passwordHash` field SHALL match the BCrypt pattern and SHALL NOT equal the original plaintext password.

**Validates: Requirements 1.1**

---

### Property 2: Duplicate email registration is always rejected

*For any* email address that has already been registered, a second registration attempt with the same email SHALL always throw a validation error and SHALL NOT create a second user record.

**Validates: Requirements 1.2**

---

### Property 3: Invalid credentials never grant access

*For any* registered user and *any* password that does not match their stored hash, the authentication attempt SHALL fail and the user SHALL NOT be granted an authenticated session.

**Validates: Requirements 1.5**

---

### Property 4: Unauthenticated requests to protected routes are always redirected

*For any* protected route in the system, an unauthenticated HTTP request SHALL receive a redirect (HTTP 302) to the login page and SHALL NOT receive the protected resource.

**Validates: Requirements 1.7**

---

### Property 5: Role-based access control is enforced for every role/route combination

*For any* authenticated user with role STUDENT, EMPLOYER, or ADMIN, and *any* route outside that role's permitted set, the request SHALL receive HTTP 403 and SHALL NOT return the protected resource. Conversely, ADMIN requests to any route SHALL never receive HTTP 403.

**Validates: Requirements 2.1, 2.2, 2.3, 2.4**

---

### Property 6: Profile data is persisted and retrievable (round-trip)

*For any* valid `ProfileDto` (non-blank fullName, contactNumber, location), calling `ProfileService.saveProfile()` and then retrieving the profile from the repository SHALL return a profile whose fields exactly match the submitted DTO values.

**Validates: Requirements 3.2, 3.4**

---

### Property 7: Invalid profile submissions are rejected without persisting data

*For any* `ProfileDto` with at least one required field (fullName, contactNumber, or location) blank or null, Bean Validation SHALL reject the submission and no profile record SHALL be created or modified.

**Validates: Requirements 3.3**

---

### Property 8: Valid resume uploads are stored and path is recorded

*For any* file with MIME type `application/pdf` or `application/vnd.openxmlformats-officedocument.wordprocessingml.document` and size ≤ 5 MB, calling `ProfileService.storeResume()` SHALL write the file to the File_Store and set `Profile.resumePath` to a non-null, non-empty path pointing to the stored file.

**Validates: Requirements 4.1**

---

### Property 9: Invalid resume uploads are always rejected

*For any* file with a MIME type other than PDF or DOCX, OR with a size exceeding 5 MB, `ProfileService.storeResume()` SHALL reject the upload with a descriptive error and SHALL NOT write any file to the File_Store or modify `Profile.resumePath`.

**Validates: Requirements 4.2, 4.3**

---

### Property 10: Re-uploading a resume replaces the previous file

*For any* Job Seeker who already has a resume on file, uploading a new valid resume SHALL result in only the new file existing in the File_Store for that user, and `Profile.resumePath` SHALL point to the new file, not the old one.

**Validates: Requirements 4.4**

---

### Property 11: Valid job listings are persisted with correct employer association

*For any* valid `JobListingDto` (all required fields present, description ≥ 50 characters) submitted by an employer, `JobService.createListing()` SHALL persist a `JobListing` whose `employer.id` equals the submitting employer's ID and whose fields match the submitted DTO.

**Validates: Requirements 5.1, 5.7**

---

### Property 12: Invalid job listing submissions are rejected without persisting data

*For any* `JobListingDto` with at least one required field (title, description, location, or experienceLevelYears) missing or invalid, Bean Validation SHALL reject the submission and no `JobListing` record SHALL be created.

**Validates: Requirements 5.2**

---

### Property 13: Ownership is enforced for all job listing mutations

*For any* `JobListing` owned by employer A, any attempt by a different employer B to update or delete that listing SHALL throw `AccessDeniedException` (HTTP 403) and SHALL NOT modify or delete the listing.

**Validates: Requirements 5.4, 5.6**

---

### Property 14: Deleting a job listing cascades to all associated applications

*For any* `JobListing` with N associated `Application` records, calling `JobService.deleteListing()` by the owning employer SHALL remove the listing AND all N associated applications from the database.

**Validates: Requirements 5.5**

---

### Property 15: Multi-filter job search returns only listings satisfying all active filters

*For any* combination of active filters (keyword, category, location, maxExperience) and *any* set of job listings in the database, `JobService.search()` SHALL return exactly those listings that satisfy every applied filter simultaneously — no listing that fails any filter SHALL appear in the results, and no listing that satisfies all filters SHALL be omitted.

**Validates: Requirements 6.1, 6.2, 6.3, 6.4, 6.5**

---

### Property 16: Unfiltered job search results are sorted by creation date descending

*For any* set of active job listings with distinct `createdAt` timestamps, calling `JobService.search()` with no filters SHALL return all active listings in descending order of `createdAt` — for any two adjacent results, the earlier result SHALL have a `createdAt` ≥ the later result's `createdAt`.

**Validates: Requirements 6.6**

---

### Property 17: New applications are created with PENDING status

*For any* Job Seeker who has a resume on file and has not previously applied to a given listing, calling `ApplicationService.apply()` SHALL create an `Application` record with `status = PENDING`, linked to the correct seeker and listing.

**Validates: Requirements 7.1, 7.5**

---

### Property 18: Duplicate applications are always rejected

*For any* (Job Seeker, Job Listing) pair where an application already exists, a second call to `ApplicationService.apply()` SHALL throw an error and SHALL NOT create a second `Application` record.

**Validates: Requirements 7.2**

---

### Property 19: Applications without a resume on file are always rejected

*For any* Job Seeker whose `Profile.resumePath` is null or empty, any call to `ApplicationService.apply()` SHALL be rejected with an error instructing the seeker to upload a resume, and no `Application` record SHALL be created.

**Validates: Requirements 7.3**

---

### Property 20: Application status transitions trigger notifications

*For any* `Application` whose status is updated to `SHORTLISTED` or `REJECTED` by `ApplicationService.updateStatus()`, the `NotificationService.notify()` method SHALL be called exactly once, an in-app `Notification` record SHALL be persisted with `read = false`, and the `JavaMailSender` SHALL be invoked with the Job Seeker's email address and the job listing title in the subject.

**Validates: Requirements 8.2, 8.3, 9.1, 9.2**

---

### Property 21: Email delivery failure does not prevent notification persistence

*For any* application status update where `JavaMailSender.send()` throws a `MailException`, the in-app `Notification` record SHALL still be persisted and no exception SHALL propagate out of `NotificationService.notify()`.

**Validates: Requirements 9.5**

---

### Property 22: Unread notifications are returned sorted by creation date descending

*For any* Job Seeker with N unread `Notification` records at varying `createdAt` timestamps, `NotificationService.getUnreadForUser()` SHALL return exactly N notifications in descending order of `createdAt` — no read notifications SHALL appear in the results.

**Validates: Requirements 9.3**

---

### Property 23: Marking a notification as read sets its read flag to true

*For any* unread `Notification` record, calling `NotificationService.markAsRead()` SHALL set `Notification.read = true` and the notification SHALL no longer appear in the unread notifications list.

**Validates: Requirements 9.4**

---

### Property 24: Dashboard aggregation counts are accurate

*For any* employer with N active job listings and M total applications (K of which are SHORTLISTED), the employer dashboard stats SHALL report exactly N active listings and M total applications with K shortlisted. For the admin dashboard, the reported totals SHALL equal the sum across all employers and all users in the system.

**Validates: Requirements 8.5, 10.1, 10.2, 10.3, 10.4**

---

### Property 25: Bean Validation errors include field-level details

*For any* form submission that fails Bean Validation, the error response SHALL contain at least one entry per violated constraint, each entry including the field name and a non-empty descriptive error message.

**Validates: Requirements 11.2**

---

### Property 26: Missing resources return HTTP 404

*For any* request for a `JobListing`, `Application`, or `Profile` by an ID that does not exist in the database, the system SHALL return HTTP 404 with a descriptive error message that does not expose internal implementation details.

**Validates: Requirements 11.5**

---

## Error Handling

### Centralized Exception Handling

All exceptions are handled by a single `@ControllerAdvice` class:

```java
@ControllerAdvice
public class GlobalExceptionHandler {

    @ExceptionHandler(ResourceNotFoundException.class)
    public String handleNotFound(ResourceNotFoundException ex, Model model) {
        model.addAttribute("message", ex.getMessage());
        return "error/404";  // HTTP 404
    }

    @ExceptionHandler(AccessDeniedException.class)
    public String handleAccessDenied(AccessDeniedException ex, Model model) {
        return "error/403";  // HTTP 403
    }

    @ExceptionHandler(DuplicateApplicationException.class)
    public String handleDuplicate(DuplicateApplicationException ex,
                                   RedirectAttributes ra) {
        ra.addFlashAttribute("error", ex.getMessage());
        return "redirect:/jobs";
    }

    @ExceptionHandler(FileStorageException.class)
    public String handleFileStorage(FileStorageException ex,
                                     RedirectAttributes ra) {
        ra.addFlashAttribute("error", ex.getMessage());
        return "redirect:/profile/edit";
    }

    @ExceptionHandler(Exception.class)
    public String handleGeneric(Exception ex, Model model) {
        log.error("Unhandled exception", ex);
        model.addAttribute("message", "An unexpected error occurred. Please try again.");
        return "error/500";  // HTTP 500 — no stack trace exposed
    }
}
```

### Custom Exception Hierarchy

```
RuntimeException
├── ResourceNotFoundException      (→ 404)
├── AccessDeniedException          (→ 403)
├── DuplicateApplicationException  (→ redirect with flash error)
├── ResumeRequiredException        (→ redirect with flash error)
├── FileStorageException           (→ redirect with flash error)
│   ├── InvalidFileTypeException
│   └── FileSizeLimitException
└── NotificationDeliveryException  (logged only, never propagated)
```

### Bean Validation Error Handling

Spring MVC's `BindingResult` is used in all form-handling controllers. When validation fails, the controller re-renders the form with inline error messages:

```java
@PostMapping("/profile/edit")
public String saveProfile(@Valid @ModelAttribute ProfileDto dto,
                           BindingResult result,
                           @AuthenticationPrincipal UserDetails user,
                           Model model) {
    if (result.hasErrors()) {
        return "profile/edit";  // re-render form with th:errors
    }
    profileService.saveProfile(getUserId(user), dto);
    return "redirect:/profile?success=true";
}
```

---

## Testing Strategy

### Dual Testing Approach

The testing strategy combines **unit/example-based tests** for specific behaviors and **property-based tests** for universal correctness guarantees.

**Property-Based Testing Library**: [jqwik](https://jqwik.net/) (JUnit 5 compatible, mature Java PBT library)

Each property-based test runs a **minimum of 100 iterations** with randomly generated inputs.

### Test Configuration

```xml
<!-- pom.xml -->
<dependency>
    <groupId>net.jqwik</groupId>
    <artifactId>jqwik</artifactId>
    <version>1.8.4</version>
    <scope>test</scope>
</dependency>
```

### Property Test Tag Format

Each property test is tagged with a comment referencing the design property:

```java
// Feature: job-portal-web-app, Property 1: Password is stored as a bcrypt hash, never plaintext
@Property(tries = 100)
void passwordIsStoredAsBcryptHash(@ForAll @From("validRegistrationDtos") RegistrationDto dto) {
    authService.register(dto);
    User saved = userRepository.findByEmail(dto.getEmail()).orElseThrow();
    assertThat(saved.getPasswordHash()).startsWith("$2a$");
    assertThat(saved.getPasswordHash()).isNotEqualTo(dto.getPassword());
}
```

### Test Coverage by Layer

#### Service Layer (Unit Tests with Mockito + jqwik)

| Test Class | Properties Covered | Test Type |
|---|---|---|
| `AuthServiceTest` | P1, P2, P3 | Property |
| `ProfileServiceTest` | P6, P7, P8, P9, P10 | Property |
| `JobServiceTest` | P11, P12, P13, P14, P15, P16 | Property |
| `ApplicationServiceTest` | P17, P18, P19, P20 | Property |
| `NotificationServiceTest` | P20, P21, P22, P23 | Property |
| `DashboardStatsTest` | P24 | Property |

#### Controller Layer (Spring MVC Test — MockMvc)

| Test Class | Properties Covered | Test Type |
|---|---|---|
| `SecurityAccessControlTest` | P4, P5 | Property (parameterized over routes) |
| `ValidationErrorResponseTest` | P25 | Property |
| `ResourceNotFoundTest` | P26 | Property |
| `GlobalExceptionHandlerTest` | Req 11.3, 11.4 | Example |

#### Integration Tests (Spring Boot Test + H2)

| Test Class | Coverage |
|---|---|
| `RegistrationLoginFlowIT` | Full registration → login → dashboard redirect flow |
| `JobApplicationFlowIT` | Post listing → apply → status update → notification flow |
| `ResumeUploadDownloadIT` | Upload → store → download round-trip |
| `AdminDashboardIT` | Admin stats accuracy across multiple users/listings |

### Unit Test Focus Areas

Unit tests (example-based) cover:
- Specific redirect URLs after login per role (Req 1.4)
- Session invalidation on logout (Req 1.6)
- Empty profile form for new users (Req 3.1)
- Resume download link presence in employer applicant view (Req 4.5)
- HTTP 500 response with generic message for unhandled exceptions (Req 11.3)

### Property Test Generators

Custom jqwik `@Provide` methods generate domain-specific test data:

```java
@Provide
Arbitrary<RegistrationDto> validRegistrationDtos() {
    return Combinators.combine(
        Arbitraries.strings().withCharRange('a', 'z').ofMinLength(3).ofMaxLength(20),
        Arbitraries.strings().alpha().ofMinLength(8).ofMaxLength(30),
        Arbitraries.of(Role.STUDENT, Role.EMPLOYER)
    ).as((name, password, role) ->
        new RegistrationDto(name + "@example.com", name, password + "1!", role)
    );
}

@Provide
Arbitrary<JobListingDto> validJobListingDtos() {
    return Combinators.combine(
        Arbitraries.strings().alpha().ofMinLength(3).ofMaxLength(100),
        Arbitraries.strings().alpha().ofMinLength(50).ofMaxLength(2000),
        Arbitraries.strings().alpha().ofMinLength(2).ofMaxLength(50),
        Arbitraries.strings().alpha().ofMinLength(2).ofMaxLength(50),
        Arbitraries.integers().between(0, 20)
    ).as((title, desc, location, category, exp) ->
        new JobListingDto(title, desc, "", location, category, exp, null)
    );
}
```

### Test Isolation

- All service-layer tests use Mockito mocks for repositories and external dependencies.
- `NotificationService` email tests mock `JavaMailSender` to avoid SMTP dependency.
- File storage tests use a temporary directory (`@TempDir`) cleaned up after each test.
- Integration tests use H2 in-memory database with `@Transactional` rollback.
