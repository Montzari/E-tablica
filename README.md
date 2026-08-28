# E-tablica

E-tablica is a simple electronic school notice board that I made as a school project.

The idea was to create a website that could be opened on a TV and used as a digital school board. Instead of printing announcements and putting them on a physical board, the school can upload images through the website and display them on a TV.

The project is intentionally simple. It was created while I was learning PHP and web development, so it is not meant to be a perfect example of production-level code.

The main idea is:

**Upload images → display them on `index.php` → use `index.php` as the TV screen.**

---

## What is E-tablica?

E-tablica is basically a digital notice board.

The website is divided into different types of users depending on what they need to do.

There are three main levels of access:

* **Visitor** — can only view the school board
* **Admin** — can upload and manage images
* **Owner** — has full control over the website

The TV uses the public part of the website. The administration area is protected by login and permissions.

The project was made for a school environment where the TV does not need to be controlled directly. Instead, someone with administrator access can upload the content from another computer, while the TV simply displays it.

---

# How it works

The project has two main sides.

The first side is the public school board.

The second side is the administration system.

The public school board is `index.php`. This is the page that is opened on the TV.

The administration system allows authorized users to log in and manage the content.

The basic workflow looks like this:

```text
Owner/Admin
     |
     v
  Login
     |
     v
 Admin Panel
     |
     v
 Upload Image
     |
     v
 Image stored on server
     |
     v
 index.php
     |
     v
     TV Screen
```

The TV does not need to log in.

It simply opens the public `index.php` page and displays the content that is available.

---

# Visitor

The Visitor is the simplest type of user.

A visitor does not have an account and does not need to log in.

The visitor side is mainly represented by `index.php`.

The purpose of this page is to display the school board.

For example, a TV in a school hallway could open:

```text
/index.php
```

and leave that page open.

The page can then be used as the digital school board.

## Visitor permissions

Visitors can:

* Open the public school board
* View the available images
* Use the website without an account

Visitors cannot:

* Open the administration panel
* Upload images
* Delete images
* Manage users
* Change website settings
* Change permissions
* Manage the Owner account
* Perform administrative actions

The visitor does not need to know anything about the administration system.

The idea is to keep the TV side as simple as possible.

---

# Admin

The Admin is a user who is allowed to manage the school board content.

An Admin has to log in before accessing the administration area.

The main purpose of the Admin role is to allow someone to upload images that should be displayed on the TV.

For example, a school employee could be given Admin access and use the administration panel to upload:

* School announcements
* Event posters
* Important information
* Reminders
* School schedules
* Other images intended for the TV

The Admin does not have complete control over the website.

This is intentional.

Someone who only needs to upload announcements should not automatically have access to everything.

## Admin permissions

An Admin can:

* Log in
* Access the administration panel
* Upload images
* Manage school board content
* Use the functions available to the Admin role

An Admin cannot normally:

* Manage the Owner
* Change the Owner account
* Create unrestricted Owner accounts
* Fully manage website settings
* Change the entire permission system
* Take complete control of the website
* Perform Owner-only operations

The Admin role is therefore a limited management role.

The basic idea is:

**Admin = content management**

---

# Owner

The Owner is the highest-level role in E-tablica.

The Owner is responsible for the whole website and has access to functionality that regular Admin users do not have.

The Owner can manage the website itself, as well as the users who are allowed to manage content.

The Owner can therefore be thought of as the person who owns and controls the E-tablica installation.

## Owner permissions

The Owner can:

* Access the administration system
* Upload images
* Manage school board content
* Manage administrators
* Manage users
* Change user roles
* Add users
* Remove users
* Manage website settings
* Perform Owner-level administrative actions
* Fully manage the website

The Owner has all of the permissions available to an Admin, plus additional permissions for managing the system itself.

The basic idea is:

**Owner = full website management**

---

# Permission hierarchy

The permission structure can be simplified like this:

```text
Visitor
   |
   | View only
   v
School Board


Admin
   |
   | Upload/manage content
   v
Administration


Owner
   |
   | Full control
   v
Website + Users + Content + Settings
```

Another simple way to describe it is:

| Role    | View board | Upload images | Manage content | Manage users | Full website control |
| ------- | ---------- | ------------- | -------------- | ------------ | -------------------- |
| Visitor | Yes        | No            | No             | No           | No                   |
| Admin   | Yes        | Yes           | Yes            | No           | No                   |
| Owner   | Yes        | Yes           | Yes            | Yes          | Yes                  |

This separation is important because not everyone who needs to upload an image should have complete access to the website.

---

# Authentication

E-tablica uses a basic login system for Admin and Owner accounts.

Visitors do not need authentication.

The authentication flow is approximately:

```text
User opens login page
        |
        v
Enters username/password
        |
        v
Credentials are checked
        |
        v
Authentication succeeds
        |
        v
PHP session is created
        |
        v
User can access protected pages
```

When an Admin or Owner logs in, the application checks their credentials.

If the credentials are accepted, a PHP session is created.

The session allows the application to remember that the user has authenticated.

The user does not need to enter their password again every time they open another administration page.

When the user logs out, the session is removed and the user is no longer authenticated.

They must log in again to access protected functionality.

---

# Authentication vs permissions

Authentication and permissions are two different things.

Authentication answers:

> **Who are you?**

Permissions answer:

> **What are you allowed to do?**

For example, an Admin can successfully log in.

That means the application knows that the person is authenticated.

However, being authenticated does not automatically mean that the user can do everything.

The application also needs to check the user's role.

For example:

```text
Logged in as Admin
        |
        v
Try to access Owner function
        |
        v
Role checked
        |
        v
Admin does not have permission
        |
        v
Access denied
```

An Owner, on the other hand, has access to Owner-level functionality.

This is how the application separates the Admin and Owner roles.

---

# Admin access

The Admin panel is intended to be used by authenticated users.

A visitor should not simply be able to open the administration page and start uploading content.

The application checks whether the user has the required authentication state and permissions.

The general concept is:

```text
Visitor
  |
  +----> index.php
  |
  +----> Admin panel -> denied


Admin
  |
  +----> login
          |
          +----> admin panel -> allowed
          |
          +----> Owner functions -> denied


Owner
  |
  +----> login
          |
          +----> admin panel -> allowed
          |
          +----> Owner functions -> allowed
```

This is the basic access control model used by the project.

---

# Image uploads

Image uploading is currently the main feature of E-tablica.

The administrator can upload an image through the administration panel.

The uploaded image is then stored by the application and becomes available to the public TV page.

The TV does not need to know who uploaded the image.

It simply loads the public page and displays the available content.

The basic process is:

```text
Admin
  |
  v
Select image
  |
  v
Upload image
  |
  v
Server receives file
  |
  v
File is stored
  |
  v
index.php loads the image
  |
  v
TV displays the image
```

This was enough for the original school project.

The goal was not to create a complete content management system.

The goal was simply to make it possible to change what is displayed on the TV without manually editing the website every time.

---

# TV screen

The `index.php` page is effectively the TV screen.

The idea is that a computer connected to a TV can open the page and leave it running.

For example:

```text
TV
 |
 +-- Computer / browser
       |
       +-- index.php
```

The page is public because the TV needs to access it without logging in.

For the best experience, the browser can be opened in fullscreen mode.

This makes the browser look more like a dedicated digital signage application.

The TV does not need access to the admin panel.

Someone else can manage the content from another computer.

---

# Project structure

The project contains several PHP files and directories.

The main files are:

* `index.php` — public page used as the TV screen
* `admin.php` — administration area
* `login.php` — login page
* `logout.php` — logs the user out
* `install.php` — initial setup
* `uploads/` — location used for uploaded images
* `data/` — project data
* `src/` — source files and supporting code

The structure is relatively simple because the application itself is small.

There are no large frontend frameworks or complicated backend services.

---

# How to install

The project requires a PHP-compatible web server.

Because this was a school project and I had almost no budget, I needed hosting that was free.

I found a free hosting provider that was mainly based around PHP, so PHP was the practical choice for this project.

After uploading the project to the server, the initial setup can be performed using:

```text
install.php
```

After the project is installed, the user can log in and access the administration area.

The exact installation process may depend on the hosting environment.

---

# Why PHP?

I used PHP mainly because of my budget and the hosting available to me.

I did not have money for paid hosting, so I needed a free option.

The free hosting provider I found was built around PHP, so PHP was the easiest technology for me to use in that environment.

It was not necessarily because PHP was the perfect technology for the project.

It was because it was practical.

I also wanted to actually finish the project instead of spending all my time setting up infrastructure.

Using PHP allowed me to get the website running on the hosting I had available.

---

# Learning experience

This project was also a learning experience for me.

I was still learning web development while creating E-tablica.

Because of that, the code is not perfect.

Some parts are cleaner than others, some parts could definitely be structured better, and there are things that I would implement differently if I started the project again today.

I was learning things such as:

* PHP
* HTML
* CSS
* Authentication
* PHP sessions
* User permissions
* File uploads
* Server-side logic
* Hosting
* Web server configuration
* Basic security
* Project structure
* Debugging

The project was useful because I was not just following a tutorial.

I had an actual thing that I wanted to build, and I had to solve problems as I went.

---

# AI assistance

Some parts of the code were written with the help of AI.

I used AI because I wanted to finish and deliver the project faster, especially when I was stuck on something or did not yet understand how to implement a particular feature.

AI helped with things such as understanding errors, writing some pieces of code, and getting ideas for implementation.

However, this was not simply a case of generating an entire project and submitting it without understanding anything.

I was learning while coding.

I tested things, changed code, fixed problems, looked at errors, and tried to understand what was happening.

Some code was therefore written manually, while other parts were created or improved with AI assistance.

I would not describe the project as professional code.

It was a school project built by someone who was still learning.

---

# Code quality

The code quality is not consistent throughout the project.

Some parts are simple because the application itself is simple.

Other parts could definitely be improved.

There may be areas where the code could be:

* Better structured
* More secure
* Easier to maintain
* Better documented
* More modular
* Better validated
* More efficient

I am leaving the project in this state intentionally because it represents what I actually built for the school project.

I may improve it in the future, but I do not currently consider it necessary to completely rewrite everything just for the sake of making it look professional.

---

# Security

One of the most important things to mention is that this project is **not really secure**.

It was made as a school project and a learning example.

It should **not** be treated as production-ready software.

There are many security areas that would need more work before using this as a serious public application.

For example:

* Authentication security
* Password handling
* Session security
* CSRF protection
* Brute-force protection
* File upload validation
* File type validation
* File size restrictions
* Input validation
* Permission checks
* Access control
* Error handling
* Server configuration
* Secure storage
* Logging
* Rate limiting

A login system by itself does not automatically make a website secure.

The same applies to file uploads.

Allowing users to upload files is something that needs to be handled very carefully in a real production application.

This project does not attempt to provide a complete security solution.

---

# Why I am leaving it like this

I will probably improve E-tablica in the future.

However, for now, I am leaving it as an example for anyone who needs something similar.

If someone needs a very simple school TV board, they can look at this project and understand the basic idea.

It can also be useful for someone who is learning PHP and wants to see a small project that includes authentication, permissions, file uploads, and a public display page.

I think it is better to leave the project available as a real example rather than pretending that it is a perfect production application.

---

# What I would use for a professional version

If I were building E-tablica as a serious product today, I would probably use a different architecture.

For the backend, I would recommend **Django**.

Django would provide a much stronger foundation for things like:

* Authentication
* User management
* Permissions
* Database models
* Forms
* CSRF protection
* Security features
* Administration
* Application structure

For the frontend, a modern frontend framework could also be used if the project needed a more interactive interface.

The exact frontend would depend on the requirements.

A professional version could be much more than just uploading images.

It could have a proper content management system where the Owner can manage everything and Admins have specific permissions.

---

# Possible future features

There are many things that could be added later.

Some ideas include:

* Automatic image slideshow
* Image rotation
* Announcement scheduling
* Start and end dates for announcements
* Text announcements
* Videos
* Different content types
* Multiple TV screens
* Different boards for different schools or rooms
* Multiple administrators
* More detailed roles
* Custom permissions
* Better user management
* Better image management
* Image previews
* Drag-and-drop uploading
* Content ordering
* Scheduled content
* Better authentication
* Password reset
* Stronger session security
* CSRF protection
* Better file validation
* Audit logs
* Activity history
* A better administration interface
* A proper database-backed content system
* A dedicated TV/display mode
* Automatic refresh
* Offline support
* More customization options

None of these features are the main focus of the current version.

The current version is intentionally much smaller.

---

# Current functionality

At the moment, the main working functionality is:

```text
Admin / Owner
      |
      v
   Login
      |
      v
Administration
      |
      v
 Upload images
      |
      v
   index.php
      |
      v
   TV screen
```

The Owner also has additional control over the website and users, while the Admin is limited mainly to content management.

The Visitor does not interact with the administration system at all.

This keeps the current project simple and easy to understand.

---

# Final note

E-tablica started as a school project.

I had a simple idea: make a digital school board that could run on a TV.

I had almost no budget, so I used free PHP hosting and built the project around the technologies available to me.

I was also still learning while building it, so the code is not perfect.

Some parts were written with AI assistance to help me finish the project faster, but I was still actively learning, testing, debugging, and understanding the code while working on it.

This project is therefore not meant to demonstrate perfect programming or production-level security.

It is simply an example of a small project that I built, learned from, and managed to get working.

I will probably improve it in the future, but for now I am leaving it here for anyone who wants to look at it, learn from it, or use the basic idea for their own project.

If you want something more professional and secure, I would recommend rebuilding the idea with a proper backend framework such as Django and, if needed, a modern frontend framework.

For now, E-tablica does what it was originally created to do:

**Upload images → display them on `index.php` → put the page on a TV → use it as a digital school board.**
