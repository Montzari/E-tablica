# E-tablica

E-tablica is a simple electronic school notice board.

The idea was to have a website that can be opened on a TV and used as a digital school board. Instead of putting notices on a physical board, images can be uploaded and displayed on the TV screen.

## How it works

For now, the project is very simple.

The main function is uploading images through the admin side and displaying them on `index.php`. The `index.php` page basically acts as the TV screen.

The main idea is:

**Upload an image → open `index.php` on a TV → show the image.**

The project also has basic login/logout and some other parts, but they are still very simple.

## How to use

1. Put the project on a PHP web server.
2. Open `install.php` to set up the project.
3. Log in through the login page.
4. Open `admin.php`.
5. Upload the images you want to display.
6. Open `index.php` on the computer or TV where you want to show the school board.
7. The uploaded images will be displayed there.

For the best experience, open `index.php` in fullscreen mode on the TV.

## Project structure

* `index.php` — the page displayed on the TV
* `admin.php` — admin page for uploading and managing images
* `login.php` — login page
* `logout.php` — logout
* `install.php` — initial setup
* `uploads/` — uploaded images
* `data/` — project data
* `src/` — source files

## Important

This project was made for a school project, so it is mostly an example and a simple prototype.

It is **not really secure** and should not be used as a serious production system without making a lot of improvements.

There are many things that would need to be improved before using something like this for a real public system, especially security, authentication, file uploads, permissions, and the general code structure.

I will probably improve this project in the future, but for now I’m leaving it as an example for anyone who needs a simple school TV board and wants to build something similar.

If you want to make something more professional and secure, I would recommend using **Django** for the backend and maybe adding a modern frontend framework if needed. This would make it easier to build proper authentication, better security, content management, and more advanced features.

For now, this project is just a simple example of:

**Upload images → display them on `index.php` → use it as a TV screen.**

## Future ideas

Some things I might add in the future:

* automatic image rotation/slideshow
* better image management
* scheduling announcements
* support for different types of content
* better authentication and permissions
* improved security
* a cleaner admin panel

For now, the project is intentionally kept simple.
