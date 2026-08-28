# E-tablica

E-tablica is a simple electronic school notice board made for a school project. The idea was to create a website that could be opened on a TV and used as a digital notice board. For now, its main function is simple: an admin can upload images, and `index.php` displays them on the TV screen.

## How to use

Upload the project to a PHP web server, run `install.php`, log in, open `admin.php`, and upload the images you want to display. Then open `index.php` on the TV, preferably in fullscreen mode.

## Authentication

The project has a basic PHP login system. The user enters a username and password on `login.php`. When the credentials are accepted, PHP creates a session and the user is allowed to access protected pages such as `admin.php`. Those pages check the session before allowing access. `logout.php` removes the session, so the user has to log in again. The public `index.php` page does not require authentication because it needs to be accessible from the TV.

## Why PHP?

I used PHP because I had almost no budget and needed free hosting. The free hosting I found was mainly based around PHP, so it was the easiest choice. I was also still learning while building this, so the code is not perfect. Some parts were written with AI assistance to help me finish the project faster, but this was not simply vibe coding. I was learning, testing, fixing problems, and trying to understand what I was writing.

## Important

This is a school project and should be treated as a prototype, not a secure production system. The authentication, file uploads, permissions, and other parts need more security and proper hardening. I may improve it later, but for now I am leaving it as an example for anyone who needs a simple TV school board. For a more professional project, I would recommend Django with a modern frontend framework.
