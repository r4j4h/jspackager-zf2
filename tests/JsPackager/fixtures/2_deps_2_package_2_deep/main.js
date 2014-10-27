/**
 * This file is testing 2 Dependencies, #1, #2, and a packaged dependency with 1 root packaged dependency
 * with one dependency.
 *
 *      #1 with a dependency on #3
 *      #2 with no dependency
 *      #3 is a root package with a dependency on #4
 *      #4 is a root package with a dependency on #5
 *      #5 with no dependency
 */

// @require dep_1.js
// @require dep_2.js

window.main = true;