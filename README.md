# Delegating App Base (DAB)

Delegating App Base (DAB) is a lightweight, flexible framework designed to keep things simple. It strips away the excess of rigid design patterns and over-engineered systems commonly found in many frameworks. DAB aims to give you full control and insight into your application’s inner workings without unnecessary complexity.

---

## Key Principles

- **Simplicity First:**  
  DAB offers a minimalistic foundation that focuses solely on essential functionality. This approach prevents bloated codebases and ensures you have complete visibility into the framework's inner workings.

- **Flexibility via Delegation:**  
  Instead of following a rigid MVC mold, DAB delegates routing and control to specialized controllers using clear patterns. Your application only takes on the complexity you introduce—nothing more.

- **Direct Insight:**  
  By keeping the base system uncomplicated, DAB reduces the dependency on extensive documentation. Developers can get hands-on insight into the application flow, fostering a deeper connection with the framework.

---

## How It Works

- **Base Controller & Delegation:**  
  DAB’s core idea is that each controller extends a simple `BaseController`. The key method `delegateRoute()` matches incoming routes against patterns (with expressive placeholders) and routes them accordingly. This lets you easily plug in or delegate functionality without layering on extra complexity.

- **Container for Dependency Injection:**  
  The `Container` class is responsible for loading classes on demand. It supports dependency injection, singleton management, and even mock overrides for testing—ensuring that you only get the components you need, when you need them.

- **Customizable Routing:**  
  The framework’s routing mechanism converts human-friendly patterns (like `/hello/{name}`) into regular expressions. This not only simplifies request handling but also makes the routing process transparent and easily debuggable.

---

## Directory Structure

DAB’s project structure is organized to separate concerns without enforcing a strict MVC split:

- **app/** – Your application logic, controllers, assets, factories, and tools.
- **core/** – The backbone of DAB, including the base controllers, container, and error-handlers.
- **lib/** – Additional libraries that you may optionally integrate.
- **logs/** – Application logs.
- **public/** – The entry point and .htaccess file.

---

## Getting Started

1. **Fork the Repository or Example App:**  
   Begin by forking the repository—or an example app repository—to serve as your starting point. Using an example app can provide inspiration and ready-to-use code that you can build upon.

2. **Clone Your Fork:**  
   Once you have your fork, clone it to your local machine. This allows you to explore the structure and develop your own implementation strategies.

3. **Understand the Flow:**  
   Open `public/index.php` to see how the container is created, namespaces are registered, and the FrontController is dispatched. This file is your entry point for handling any request and understanding the overall workflow.

4. **Experiment with Routing:**  
   In your controllers (for example, within `FrontController`), look at how `delegateRoute()` is used to match and hand off routes. Try adding your own route patterns and controllers to explore the flexibility of the system.

5. **Extend as Needed:**  
   Whether you’re building a small utility or a complex application, DAB’s design lets you scale the complexity only where necessary. Create new controllers, integrate libraries, and add factories without being constrained by a rigid architecture.

---

## By Analogy

If you're accustomed to other frameworks, you may find that some familiar concepts are absent in DAB. Here’s a guide on where to put your code and how to think about its structure compared to what you might be used to:

### Routing

- Routes are handled directly within the `handle` method of your controllers.  
- In DAB, the controller’s `handle` method acts as a prefix router, delegating sub-routes to other controllers.  
- If a controller cannot successfully route the request (its delegation method returns false), then the parent controller can either provide a fallback or simply return an error (like a 404).  
- This model keeps the routing logic straightforward and visible in the controller, rather than hidden away in configuration files or a separate routing layer.

### Middleware

- There is no explicitly defined middleware layer in DAB.  
- Instead of having a dedicated middleware mechanism, simply add any necessary logic at the top of your controller’s `handle` method to process the incoming request.  
- When needing to reuse common tasks (authentication, logging, validation, etc.), consider creating a helper class in the `app/tools/` folder.  
- If you prefer to conceptually group middleware behavior, feel free to create a dedicated `app/middleware/` folder; this is just a suggestion, DAB leaves its structure entirely in your hands.

### Dependency Injection

- DAB provides a simple and powerful dependency injection container (`Core\Container`) that handles class resolution and autoloading.  
- Instead of the individual dependencies, the container class simply injects itself into every constructor.
- The container serves as a central “toolbox” where dependencies are registered and retrieved, ensuring that components remain loosely coupled and easy to test or swap (via mock overrides).  
- By default the constructor treats every class as a singleton. Classes can still be instantiated the traditional way, such as `Error` (extends `Exception`).

### Error Handling

- Error handling is built into the core with a focus on transparency.  
- Instead of hidden error funnels, DAB provides both a general error handler and a development-specific one (e.g., `Core\ErrorHandler` vs. `Core\DevelopmentErrorHandler`).  
- Developers can easily see error reporting behavior and modify or extend it as needed. Consider this as having a clear diagnostic window rather than a black box.

### Configuration and Environment

- DAB encourages a deliberate yet simplified approach to configuration.  
- Rather than having a labyrinth of configuration files, keep essential settings clear and accessible within your code or in simple external files.
- This approach gives you full control over your environment without the burden of navigating complex, nested configuration files.
- Database settings are stored in `config/database.php`. Use `config/database.example.php` as a template for your local configuration.

### Asset Management

- Static files (images, stylesheets, scripts) are kept in a dedicated directory within `app/assets/static`.
- DAB uses an abstracted asset pipeline handled by the `AssetController`.
- In the base project, serving a static page, dynamic assets are not yet implemented. Check out the example apps.

### Front-End Component Helpers

A minimal JavaScript helper library is provided at `/assets/dab-components.js`.
It offers a tiny framework for building widgets:

- `DAB.h(tag, props, ...children)` creates DOM nodes.
- `DAB.BaseComponent` is a base class for widgets. Implement `render()` and
  call `mount(element)` to insert it. Call `update()` to manually rebuild the
  DOM when state changes.
- `DAB.api(url, options)` wraps `fetch` and returns JSON if available.

DOM rebuilding is manual; components do not automatically re-render on state
updates.

### Testing and Extensibility

- DAB’s simplicity makes unit testing and system testing more straightforward.  
- Due to the transparent dependency injection, you can easily swap out components for mocks or stubs by using the container’s mock registration (`setMock`).  
- It is intended that you utilize the routing structure to isolate testable components. Simply select them by setting the route in the `FrontController`'s input.

---

### License  
The DAB (Delegating App Base) software is collectively owned by the DAB Community, which includes anyone who contributes to the Software or its ecosystem. The Software is open-source but proprietary, and its use is subject to the terms of the [DAB Community License](/license).  

Key points:  
- The Software is not free of charge; Users are encouraged to pay a reasonable share of the profit they generate, though payment is not actively enforced.  
- Contributions grant collective ownership, and Contributors may be financially compensated if funds become available.  
- For practical purposes, Bram "Vectasus" Luiken manages the Community, finances, and repository.  

---
Whether you're a beginner or an experienced developer, DAB provides a system that prioritizes clarity, control, and flexibility—allowing your projects to remain as lean or as intricate as required. Start building with DAB today and experience the difference!