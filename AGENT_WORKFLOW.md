# Collaboration Workflow

Follow this workflow for feature work with the user. `AGENTS.md` remains the source of framework, Laravel, testing, and repository-wide technical rules; this file defines how to plan and deliver work with the user.

## Communication and Approval

- Communicate with the user in Arabic. Keep code identifiers, route names, and error codes unchanged.
- Treat review questions and planning discussions as read-only unless the user explicitly asks for implementation.
- Before implementation, present an ordered task breakdown with each task's goal, scope, dependencies, and completion criteria. Wait for the user's approval or comments before editing feature code.
- Work on one approved task at a time. After completing it, report the files changed, behavior delivered, verification performed, and remaining tasks. Stop and wait for the user's direction before starting the next task.
- If a material product or API behavior is unresolved, explain the options and ask only about that decision before implementing the affected task.

## Start of Each Task

1. Inspect the current repository state, project instructions, and the relevant implementation and tests. Do not rely on assumptions from earlier turns when files may have changed.
2. Distinguish existing user changes from changes made for the current task. Preserve user changes; never reset, overwrite, or include unrelated work.
3. State the task's scope and acceptance criteria before editing if they have not already been approved.
4. Implement only that task. Avoid unrelated cleanup, architectural changes, and feature work assigned to later tasks.

## Verification and Reporting

- Follow the testing instructions in `AGENTS.md`. Add meaningful regression coverage and run the narrowest affected tests for behavior changes.
- Run the required formatter after modifying PHP files. Do not claim verification that was not performed.
- At the end of each task, report its outcome in Arabic, including changed files, tests and their results, any limitations, and the next task awaiting the user's direction.
- Do not start the full test suite or another task automatically when project or user instructions require the user to review first.

## Product Image Feature Decisions

Treat these as the agreed requirements unless the user changes them:

- Store original image files on the configured local public disk. The deployment target is a durable, single application instance.
- A product has between 1 and 8 images. Position `0` is the primary image.
- Product creation accepts its initial images. Product `PATCH` updates product data only and rejects image uploads.
- Keep a separate endpoint for adding images after product creation. It also appends new images.
- Deleting an image reindexes the remaining images. The final image cannot be deleted.
- Reordering requires the IDs of all images belonging to that product exactly once, in the desired order.
- Accept JPG, JPEG, PNG, and WebP images up to 5 MB each. Do not generate thumbnails or image variants unless the user asks.

## Product Image Task Order

Keep this numbering stable unless the user approves a revised plan:

1. Read-only inspection of the current repository state, relevant instructions, product implementation, and tests; report findings and confirm scope.
2. Add image persistence: migration, model, product relationship, and factory where appropriate.
3. Support image uploads during product creation, including validation, storage, failure cleanup, and response data.
4. Support appending images through the separate post-creation add-images endpoint. Keep product `PATCH` limited to product data and reject images there.
5. Support image deletion, protect the final image, and reindex remaining positions.
6. Support image reordering with complete ownership and uniqueness validation.
7. Complete response and lifecycle integration, including product image visibility, cleanup when deleting a product, and final affected integration checks.

Each task includes its own relevant tests and verification. Do not begin a later task until the user reviews the current task and asks to continue.
