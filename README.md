# Dixeo Student Tutor

The **Dixeo Student Tutor** is an AI-powered Moodle block that provides learers with a 24/7 multilingual teacher grounded in published course content, with the goal of helping learners understand concepts, locate information, summarize resources, and receive contextual assistance throughout their learning journey. 

Unlike a general-purpose chatbot, the Dixeo Student Tutor is grounded in the course knowledge base, reducing hallucinations and ensuring responses remain relevant to the course.  

The tutor is available throughout the course (except on excluded activities) and can be displayed either as a floating assistant or within the Moodle block drawer. The student can use the AI tutor without ever quitting Moodle.

# Features

- Grounded in course content and files published by the teacher
- Retrieval-Augmented Generation (RAG) using course resources
- Multilingual conversations with automatic translation of course content
- Persistent, individualised user conversations over the lifetime of the course enrolment
- Responsive user interface
- Accessible interface (ARIA compliant)
- Easy, per-course, teacher-controlled deployment (simply add the block to a course)

# Requirements

- **Moodle:** 4.5 or later
- **Dependency:** `local_dixeo` 4.1.0 or later and a valid Dixeo API key

# Installation

1. Copy `dixeo_tutor` to `/blocks/dixeo_tutor/`
2. Visit Site Administration > Notifications
3. Complete the Moodle upgrade.
4. Make sure that Dixeo AI has been configured with a valid Dixeo API key.

# Configuration

The plugin provides the following administrator settings.

- **Display Mode** : Choose how students access the tutor.
 - **Popup** (default) - displays a floating assistant accessible from every supported course page
 - **Block Drawer** - displays the tutor as a block in the block drawer
- **Excluded Module Types** : Define activity modules where the tutor should not appear.
 -Example: 
  ```
  quiz,simplequiz2
  ```

# Adding the tutor to a course

Teachers with permission to manage blocks can add the tutor to a course:
1. Turn editing on.
2. Add **Dixeo Student Tutor**.
3. Save.

After adding the tutor to a course:
- File synchronisation is automatically activated for the course.
- Students immediately gain access to the tutor.

# Student Experience

Students simply open the tutor and ask questions such as:
- Explain this concept.
- Summarise today's lesson.
- What does this document mean?
- Where can I find information about…?
- Can you explain this in simpler terms?
- Translate this explanation.

The tutor only answers using the course knowledge base supplied through Dixeo.

# Teacher Experience

Teachers can use the tutor to:
- support self-directed learning;
- reduce repetitive questions;
- encourage exploration of course resources;
- provide 24/7 learner assistance.

Teachers can also interact with the tutor using to verify the contents of their course.

# Display Behaviour

The tutor is unavailable when:
- Moodle editing mode is enabled;
- The user is not enrolled in the course, or does not have the `local/dixeo:talktotutor`;
- The page belongs to an excluded activity type (admin settings);

# Capabilities

| Capability | Description | Default Roles |
|------------|-------------|---------------|
| `block/dixeo_tutor:addinstance` | Add the Tutor block | Editing Teacher, Manager |
| `local/dixeo:talktotutor` | Interact with the AI Tutor | Student, Teacher, Editing Teacher, Manager |

# Accessibility

The interface includes accessibility support including:
- ARIA labels
- keyboard navigation
- accessible message regions
- screen reader support
- accessible chat controls

# Privacy

Tutor conversations are held by the Dixeo service, so GDPR export and erasure requests are served through the Dixeo API rather than from the Moodle database. If the API is unreachable while an export runs, Moodle still marks the request complete and the archive simply carries no tutor conversation: site administrators receive a notification, and the request has to be submitted again once the API is back.

Erasure requests need no manual replay. An unreachable API queues a retry that Moodle keeps re-attempting for roughly twenty days.

# Support

For support, documentation, or licensing information, contact the Dixeo Team: support@dixeo.com

## License

GNU GPL v3 or later
Copyright (C) 2026 Edunao

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License.
Copyright © 2025 Edunao SAS
