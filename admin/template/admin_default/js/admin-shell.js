/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */
(function(window, document) {
    function ready(callback) {
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", callback);
            return;
        }

        callback();
    }

    function toArray(list) {
        return Array.prototype.slice.call(list || []);
    }

    function normalize(value) {
        return String(value || "").toLowerCase();
    }

    ready(function() {
        var body = document.body;
        var sidebar = document.querySelector("[data-admin-sidebar]");
        var toggle = document.querySelector("[data-admin-menu-toggle]");

        if (toggle) {
            toggle.addEventListener("click", function() {
                body.classList.toggle("admin-nav-open");
            });
        }

        document.addEventListener("click", function(event) {
            if (window.innerWidth > 980 || !body.classList.contains("admin-nav-open") || !sidebar || !toggle) {
                return;
            }

            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                body.classList.remove("admin-nav-open");
            }
        });

        var navGroups = toArray(document.querySelectorAll("#menu > li"));
        navGroups.forEach(function(group) {
            var sub = group.querySelector("ul.sub");
            var trigger = group.querySelector("a.exp");
            var hasActive = !!group.querySelector("a#current, li.active");

            if (hasActive && sub) {
                group.classList.add("is-open");
                sub.style.display = "block";
            }

            if (trigger && sub) {
                trigger.addEventListener("click", function(event) {
                    event.preventDefault();
                    group.classList.toggle("is-open");
                    sub.style.display = group.classList.contains("is-open") ? "block" : "none";
                });
            }
        });

        var navSearch = document.querySelector("[data-nav-search]");
        if (navSearch) {
            var applyNavFilter = function() {
                var query = normalize(navSearch.value);

                navGroups.forEach(function(group) {
                    var groupLabel = normalize(group.getAttribute("data-nav-label"));
                    var subItems = toArray(group.querySelectorAll("ul.sub > li"));
                    var groupMatched = !query || groupLabel.indexOf(query) !== -1;
                    var visibleSubItems = 0;

                    subItems.forEach(function(item) {
                        var label = normalize(item.getAttribute("data-nav-label"));
                        var matched = !query || groupMatched || label.indexOf(query) !== -1;

                        item.setAttribute("data-nav-hidden", matched ? "false" : "true");
                        if (matched) {
                            visibleSubItems += 1;
                        }
                    });

                    var shouldShow = !query || groupMatched || visibleSubItems > 0;
                    group.setAttribute("data-nav-hidden", shouldShow ? "false" : "true");

                    if (!shouldShow) {
                        return;
                    }

                    if (query) {
                        group.classList.add("is-open");
                        if (subItems.length) {
                            group.querySelector("ul.sub").style.display = "block";
                        }
                    }
                });
            };

            navSearch.addEventListener("input", applyNavFilter);
            applyNavFilter();
        }

        var settingsSearch = document.querySelector("[data-settings-search]");
        var settingsGrid = document.querySelector("[data-settings-grid]");

        if (settingsGrid) {
            var cards = toArray(settingsGrid.querySelectorAll("[data-settings-card]"));
            var emptyState = document.querySelector("[data-settings-empty]");
            var countLabel = document.querySelector("[data-settings-count]");
            var filterButtons = toArray(document.querySelectorAll("[data-settings-filter]"));
            var activeGroup = "all";

            var renderSettings = function() {
                var query = normalize(settingsSearch ? settingsSearch.value : "");
                var visibleCards = 0;

                cards.forEach(function(card) {
                    var group = normalize(card.getAttribute("data-settings-group"));
                    var haystack = normalize(card.getAttribute("data-settings-search"));
                    var matchesGroup = activeGroup === "all" || group === activeGroup;
                    var matchesQuery = !query || haystack.indexOf(query) !== -1;
                    var visible = matchesGroup && matchesQuery;

                    card.setAttribute("data-settings-hidden", visible ? "false" : "true");
                    if (visible) {
                        visibleCards += 1;
                    }
                });

                if (countLabel) {
                    countLabel.textContent = visibleCards + " areas";
                }

                if (emptyState) {
                    emptyState.classList.toggle("is-visible", visibleCards === 0);
                }
            };

            if (settingsSearch) {
                settingsSearch.addEventListener("input", renderSettings);
            }

            filterButtons.forEach(function(button) {
                button.addEventListener("click", function() {
                    activeGroup = normalize(button.getAttribute("data-settings-filter")) || "all";

                    filterButtons.forEach(function(otherButton) {
                        otherButton.classList.toggle("is-active", otherButton === button);
                    });

                    renderSettings();
                });
            });

            renderSettings();
        }
    });
})(window, document);
