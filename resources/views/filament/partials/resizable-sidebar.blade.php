{{--
    Makes the vertical divider between the Filament sidebar and the page
    content draggable, on both the /admin and /seller panels.

    Filament already drives the sidebar width off a single custom property:
    `layout/base.blade.php` emits `:root { --sidebar-width: 20rem }` and the
    sidebar element carries Tailwind's `w-[--sidebar-width]`, which compiles to
    `width: var(--sidebar-width)`. The main content sits next to it in a flex
    row, so it reflows on its own. That means resizing is just "write a new
    value for --sidebar-width" -- no parallel width mechanism, no vendor edits.

    This renders at HEAD_END. The stored width is applied to <html> as an inline
    style before the body is parsed, so there is no flash of the default 20rem
    on load. Inline styles beat the :root rule regardless of source order.

    The handle itself is a body-level fixed element positioned at
    `inset-inline-start: var(--sidebar-width)`, injected by JS. It is not part
    of the sidebar's own markup because the sidebar's inner wrapper is
    `overflow-x-clip`, which would cut off anything hanging over its edge.
--}}
<style>
    @media (min-width: 1024px) {
        .fi-sidebar-resize-handle {
            position: fixed;
            top: 0;
            bottom: 0;
            inset-inline-start: var(--sidebar-width);
            width: 9px;
            margin-inline-start: -4px;
            z-index: 21; /* above the sticky topbar (z-20), below modals (z-40) */
            cursor: col-resize;
            background-color: transparent;
            touch-action: none;
            -webkit-user-select: none;
            user-select: none;
        }

        /* The visible 2px line, drawn on top of the sidebar's own 1px border. */
        .fi-sidebar-resize-handle::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            inset-inline-start: 3px;
            width: 2px;
            background-color: transparent;
            transition: background-color 120ms ease-in-out;
        }

        .fi-sidebar-resize-handle:hover::before,
        .fi-sidebar-resize-handle:focus-visible::before,
        .fi-sidebar-resize-handle[data-dragging]::before {
            background-color: rgb(113 113 122);
        }

        .dark .fi-sidebar-resize-handle:hover::before,
        .dark .fi-sidebar-resize-handle:focus-visible::before,
        .dark .fi-sidebar-resize-handle[data-dragging]::before {
            background-color: rgb(161 161 170);
        }

        .fi-sidebar-resize-handle:focus-visible {
            outline: none;
        }

        /* While dragging: kill text selection and stop the pointer from being
           swallowed by iframes (rich-editor / markdown preview panes). */
        body.fi-sidebar-resizing {
            cursor: col-resize;
            -webkit-user-select: none;
            user-select: none;
        }

        body.fi-sidebar-resizing iframe {
            pointer-events: none;
        }
    }

    /* Below lg the sidebar is an off-canvas overlay with a fixed width -- there
       is no divider to drag, so hide the handle entirely. */
    @media (max-width: 1023.98px) {
        .fi-sidebar-resize-handle {
            display: none;
        }
    }
</style>

<script>
    (function () {
        if (window.filamentSidebarResizeInitialised) {
            return
        }
        window.filamentSidebarResizeInitialised = true

        var STORAGE_KEY = 'filament-sidebar-width'
        var MIN_WIDTH = 180
        var MAX_WIDTH = 480
        var root = document.documentElement
        var currentWidth = null

        function clamp(px) {
            return Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, Math.round(px)))
        }

        function applyWidth(px) {
            currentWidth = clamp(px)
            root.style.setProperty('--sidebar-width', currentWidth + 'px')
        }

        function readStoredWidth() {
            try {
                var stored = parseInt(localStorage.getItem(STORAGE_KEY), 10)

                return isNaN(stored) ? null : stored
            } catch (error) {
                return null // private mode / storage disabled
            }
        }

        function storeWidth(px) {
            try {
                localStorage.setItem(STORAGE_KEY, String(px))
            } catch (error) {
                // Nothing to do -- the width still applies for this page.
            }
        }

        function resetWidth() {
            currentWidth = null
            root.style.removeProperty('--sidebar-width')

            try {
                localStorage.removeItem(STORAGE_KEY)
            } catch (error) {
                //
            }
        }

        // Runs while <head> is still parsing, so the saved width is in place
        // before the sidebar ever paints.
        var storedWidth = readStoredWidth()

        if (storedWidth !== null) {
            applyWidth(storedWidth)
        }

        function ensureHandle() {
            // Login/registration pages render no sidebar -- nothing to resize.
            if (!document.querySelector('.fi-main-sidebar')) {
                return
            }

            if (document.querySelector('.fi-sidebar-resize-handle')) {
                return
            }

            var handle = document.createElement('div')
            handle.className = 'fi-sidebar-resize-handle'
            handle.setAttribute('role', 'separator')
            handle.setAttribute('aria-orientation', 'vertical')
            handle.setAttribute('aria-label', 'Resize the navigation sidebar')
            handle.setAttribute('aria-valuemin', String(MIN_WIDTH))
            handle.setAttribute('aria-valuemax', String(MAX_WIDTH))
            handle.setAttribute('title', 'Drag to resize. Double-click to reset.')
            handle.tabIndex = 0

            document.body.appendChild(handle)
        }

        function measuredWidth() {
            if (currentWidth !== null) {
                return currentWidth
            }

            var sidebar = document.querySelector('.fi-main-sidebar')

            return sidebar ? sidebar.getBoundingClientRect().width : 320
        }

        var isDragging = false
        var activeHandle = null

        function widthFromPointer(clientX) {
            // In RTL the sidebar hangs off the right edge, so the width is the
            // distance from the viewport's right side instead.
            return getComputedStyle(root).direction === 'rtl'
                ? window.innerWidth - clientX
                : clientX
        }

        // Delegated listeners on `document`, so a handle re-injected after a
        // Livewire navigation is wired up without rebinding anything.
        document.addEventListener('pointerdown', function (event) {
            if (!event.target.closest) {
                return
            }

            var handle = event.target.closest('.fi-sidebar-resize-handle')

            if (!handle || event.button !== 0) {
                return
            }

            isDragging = true
            activeHandle = handle
            handle.setAttribute('data-dragging', '')
            document.body.classList.add('fi-sidebar-resizing')

            try {
                handle.setPointerCapture(event.pointerId)
            } catch (error) {
                // Pointer capture is a nicety, not a requirement.
            }

            event.preventDefault()
        })

        document.addEventListener('pointermove', function (event) {
            if (!isDragging) {
                return
            }

            applyWidth(widthFromPointer(event.clientX))
            event.preventDefault()
        })

        function endDrag() {
            if (!isDragging) {
                return
            }

            isDragging = false
            document.body.classList.remove('fi-sidebar-resizing')

            if (activeHandle) {
                activeHandle.removeAttribute('data-dragging')
                activeHandle = null
            }

            if (currentWidth !== null) {
                storeWidth(currentWidth)
            }
        }

        document.addEventListener('pointerup', endDrag)
        document.addEventListener('pointercancel', endDrag)

        document.addEventListener('dblclick', function (event) {
            if (!event.target.closest) {
                return
            }

            if (event.target.closest('.fi-sidebar-resize-handle')) {
                resetWidth()
            }
        })

        document.addEventListener('keydown', function (event) {
            if (!event.target.closest) {
                return
            }

            if (!event.target.closest('.fi-sidebar-resize-handle')) {
                return
            }

            var step = event.shiftKey ? 48 : 16
            var isRtl = getComputedStyle(root).direction === 'rtl'
            var delta = 0

            if (event.key === 'ArrowLeft') {
                delta = isRtl ? step : -step
            } else if (event.key === 'ArrowRight') {
                delta = isRtl ? -step : step
            } else if (event.key === 'Home') {
                delta = MIN_WIDTH - measuredWidth()
            } else if (event.key === 'End') {
                delta = MAX_WIDTH - measuredWidth()
            } else if (event.key === 'Escape') {
                resetWidth()
                event.preventDefault()

                return
            } else {
                return
            }

            applyWidth(measuredWidth() + delta)
            storeWidth(currentWidth)
            event.preventDefault()
        })

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ensureHandle)
        } else {
            ensureHandle()
        }

        // Livewire SPA navigation swaps the <body>, which takes the handle with
        // it and can drop the inline width off <html>. Re-apply both.
        document.addEventListener('livewire:navigated', function () {
            if (currentWidth !== null) {
                root.style.setProperty('--sidebar-width', currentWidth + 'px')
            }

            ensureHandle()
        })
    })()
</script>
