// This function defines and initializes the global store, but doesn't initialize Pinia here
function initializeGlobalStore() {
    // Ensure Vue and Pinia are loaded correctly
    if (typeof Pinia === 'undefined') {
        console.error('Pinia is not loaded correctly.');
        return;
    }

    // Define the store using Pinia (no need to initialize Pinia here, it should already be done in the parent app)
    const useGlobalStore = Pinia.defineStore('global', {
        state: () => ({
            isRtl: false,
            isMobile: false,
            isTablet: false,
            breakpoint: 'desktop'
        }),
        actions: {
            setRtl(value) {
                if (this.isRtl !== value) {  // Only update if it's different
                    this.isRtl = value;
                    document.documentElement.setAttribute('dir', value ? 'rtl' : 'ltr');
                }
            },
            setMobile(value) {
                this.isMobile = value;
            },
            setTablet(value) {
                this.isTablet = value;
            },
            setBreakpoint(value) {
                if (this.breakpoint !== value) {
                    this.breakpoint = value;
                    document.documentElement.setAttribute('data-bp', value);
                }
            }
        }
    });

    // Expose the store creation function to the global scope (if needed)
    window.useGlobalStore = useGlobalStore;

    // Function to initialize global store with state updates
    function initGlobalStore() {
        const globalStore = useGlobalStore();  // Create the store after Vue and Pinia are initialized
        const isRtl = document.documentElement.getAttribute('dir') === 'rtl';
        globalStore.setRtl(isRtl);  // Set the initial RTL value

        // Detect mobile view by checking window width
        function detectBreakpoint() {
            const width = window.innerWidth;
            let breakpoint = 'desktop';
            let isMobile = false;
            let isTablet = false;

            if (width <= 640) {
                breakpoint = 'mobile';
                isMobile = true;
            } else if (width <= 1024) {
                breakpoint = 'tablet';
                isTablet = true;
            }

            globalStore.setMobile(isMobile);
            globalStore.setTablet(isTablet);
            globalStore.setBreakpoint(breakpoint);
        }

        // Run mobile detection and update state on resize
        detectBreakpoint();
        window.addEventListener('resize', detectBreakpoint);

        // RTL Mutation Observer: Detect changes to the 'dir' attribute only on the <html> tag
        const observer = new MutationObserver(() => {
            const currentRtlValue = document.documentElement.getAttribute('dir') === 'rtl';
            globalStore.setRtl(currentRtlValue);  // Update the store when 'dir' changes
        });

        // Start observing changes to the 'dir' attribute on the <html> element only
        observer.observe(document.documentElement, {
            attributes: true,  // Watch for attribute changes
            attributeFilter: ['dir'],  // Only watch for changes to the 'dir' attribute
        });

        // Expose the store to the window object
        window.globalStore = globalStore;
    }

    // Run the initialization
    initGlobalStore();
}
