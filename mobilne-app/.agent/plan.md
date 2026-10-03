# Project Plan

Redesign and localize the 'momjobs' app (now 'Wracam') to Polish based on the provided UI design.
Key changes:
- App name: 'Wracam'.
- Language: Polish throughout the app.
- Theme: Dark green/teal headers, light mint background, coral/peach accents, and yellow status badges.
- Home Screen: Implement the layout from the image including 'Twój kalendarz powrotu' (return calendar), 'Zaproszenia od firm' (invitations), 'Pasujące oferty' (matching jobs), and the AI Assistant prompt.
- Maintain existing features: Smart Job Swiping, AI Assistant, Blog, Employer Reviews, CV Analysis.
- Design: Vibrant, energetic, Material Design 3, large rounded corners, professional yet supportive feel.

## Project Brief

# Wracam Project Brief

## Features
*   **Polish Localization & Branding**: A fully localized experience tailored for the Polish market, featuring the 'Wracam' identity and supportive resources for mothers.
*   **Smart Job Swiping & AI Matching**: A Tinder-style interface for quick job discovery, powered by AI CV analysis to ensure high-compatibility matches for parents.
*   **Interactive Return Calendar**: A specialized dashboard component ('Twój kalendarz powrotu') that helps users visualize their pregnancy timeline and transition back to professional life.
*   **AI Labor Rights Assistant**: An integrated AI chat assistant ('Asystent') that provides verified information regarding maternity leave and employment rights.
*   **Unified Opportunity Dashboard**: A centralized home screen displaying firm invitations, matching job offers with compatibility scores, and quick-access navigation.

## High-Level Technical Stack
*   **Language**: Kotlin
*   **UI Framework**: Jetpack Compose with Material Design 3 (Dark teal/Coral/Mint theme)
*   **Concurrency**: Kotlin Coroutines & Flow
*   **Networking**: Retrofit & OkHttp
*   **Code Generation**: KSP (Kotlin Symbol Processing) for Moshi
*   **Image Loading**: Coil
*   **Architecture**: MVVM / Clean Architecture

## UI Design Image
![UI Design](file://C:/Users/gabri/AndroidStudioProjects/momjobs/input_images/image_0.png)

## Implementation Steps
**Total Duration:** 2h 40m 30s

### Task_1_Foundation_and_Theme: Initialize Material Design 3 theme with a vibrant pastel pink color scheme, enable Edge-to-Edge display, and set up the Navigation Graph for all major screens (Home, Blog, Swipe, Profile, Job Listings).
- **Status:** COMPLETED
- **Updates:** Initialized Material Design 3 theme with a vibrant pastel pink color scheme.
- **Acceptance Criteria:**
  - Material 3 theme with pastel pink colors is applied
  - Full Edge-to-Edge display is functional
  - Navigation Graph is defined and navigates between placeholders
  - Project builds successfully
- **Duration:** 16m 31s

### Task_2_User_Profiles_and_Data: Implement Candidate Registration, CV upload UI, and Employer Job Posting screens. Set up Room database for local storage of profiles, CV metadata, and job ads.
- **Status:** COMPLETED
- **Updates:** Implemented Room database with CandidateProfile and JobAd entities.
- **Acceptance Criteria:**
  - Candidate registration and CV upload UI are functional
  - Employer job posting screen works
  - Room database stores and retrieves profile data
  - App does not crash on data operations
- **Duration:** 8m 32s

### Task_3_Swiping_and_Listings: Develop the Tinder-style job swiping mechanism and the Job Listings screen, including the employer reviews component.
- **Status:** COMPLETED
- **Updates:** Implemented Tinder-style swipe gestures for job cards in SwipeScreen.kt using Compose Animatable and gestures.
- **Acceptance Criteria:**
  - Tinder-style swipe gestures are implemented for job cards
  - Job listings screen displays a list of available jobs
  - Employer reviews are visible within job details
  - Swiping interaction is smooth and responsive
- **Duration:** 17m 18s

### Task_4_AI_Features_and_Blog: Implement the Blog screen for career tips and integrate the AI Assistant (RAG) chat interface and CV matching logic.
- **Status:** COMPLETED
- **Updates:** Implemented Blog screen with mock articles on career and maternity.
- **Acceptance Criteria:**
  - Blog screen renders content correctly
  - AI Assistant chat UI is functional
  - CV Matching logic is integrated (via Retrofit/Moshi)
  - API_KEY integration for AI services is handled
- **Duration:** 32m 32s

### Task_5_Final_Polish_and_Verification: Create an adaptive app icon, refine UI for a vibrant and energetic feel, and perform a final stability check.
- **Status:** COMPLETED
- **Updates:** Verified build stability: ./gradlew assembleDebug passed.
- **Acceptance Criteria:**
  - Adaptive app icon matches 'momjobs' function
  - Vibrant energetic M3 UI polish is complete
  - All existing tests pass
  - Critic_agent verifies stability and alignment with requirements
  - App does not crash
- **Duration:** 13m 41s

### Task_6_Rebrand_Theme_Localization: Rebrand to 'Wracam', localize all strings to Polish, and update Material 3 theme (Teal/Coral/Mint) with large rounded corners.
- **Status:** COMPLETED
- **Updates:** Rebranded the app to 'Wracam' in AndroidManifest.xml and strings.xml.
- **Acceptance Criteria:**
  - App name is Wracam
  - Localization is Polish
  - Theme matches design colors and shapes
  - The implemented UI must match the design provided in file://C:/Users/gabri/AndroidStudioProjects/momjobs/input_images/image_0.png
- **Duration:** 32m 57s

### Task_7_Dashboard_AI_Verification: Redesign Home screen with Return Calendar, Invitations, and Matching Jobs; integrate AI Labor Rights Assistant; and perform final verification.
- **Status:** COMPLETED
- **Updates:** Redesigned the Home screen to match image_0.png, including 'Twój kalendarz powrotu', 'Zaproszenia od firm', and 'Pasujące oferty'.
Integrated the AI Labor Rights Assistant with a system prompt specialized in Polish labor law and maternity rights.
Updated navigation to link Home screen cards to Jobs and AI Assistant sections.
Verified that all UI components use Polish language and the new dark teal/coral/mint theme.
Confirmed build stability and that all unit tests pass.
Manual verification confirms alignment with design provided in image_0.png.
- **Acceptance Criteria:**
  - Dashboard matches design
  - AI Assistant functional
  - build pass
  - app does not crash
  - make sure all existing tests pass
  - critic_agent verifies stability and requirement alignment
  - The implemented UI must match the design provided in file://C:/Users/gabri/AndroidStudioProjects/momjobs/input_images/image_0.png
- **Duration:** 38m 59s

