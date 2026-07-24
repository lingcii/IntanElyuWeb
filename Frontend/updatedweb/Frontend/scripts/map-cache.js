(function () {
    'use strict';

    window.MAP_CACHE = {
        municipalities: null,
        touristSpots: null,

        async getMunicipalities() {
            if (this.municipalities) return this.municipalities;

            const saved = sessionStorage.getItem('map_cache_municipalities');
            if (saved) {
                this.municipalities = JSON.parse(saved);
                return this.municipalities;
            }

            try {
                const data = await window.API_CONFIG.get(`${window.API_CONFIG.BASE_URL}/api/municipalities`);
                const munis = data.municipalities || data.data || data;
                this.setMunicipalities(munis);
                return munis;
            } catch (e) {
                console.error('Failed to fetch municipalities for map cache', e);
                return [];
            }
        },

        setMunicipalities(data) {
            this.municipalities = data;
            sessionStorage.setItem('map_cache_municipalities', JSON.stringify(data));
        },

        async getTouristSpots(role = 'picto') {
            if (this.touristSpots) return this.touristSpots;

            const saved = sessionStorage.getItem('map_cache_tourist_spots');
            if (saved) {
                this.touristSpots = JSON.parse(saved);
                return this.touristSpots;
            }

            try {
                let endpoint;
                if (role === 'picto' || role === 'pitco') {
                    endpoint = 'pitco/tourist-spots';
                } else if (role === 'lupto') {
                    endpoint = 'lupto/tourist-spots';
                } else if (role === 'municipal' || role.endsWith('_mto')) {
                    endpoint = 'municipal/tourist-spots';
                } else {
                    endpoint = 'lupto/tourist-spots';
                }

                const data = await window.API_CONFIG.get(`${window.API_CONFIG.BASE_URL}/api/${endpoint}`);
                const spots = data.data || data;
                this.setTouristSpots(spots);
                return spots;
            } catch (e) {
                console.error('Failed to fetch tourist spots for map cache', e);
                return [];
            }
        },

        setTouristSpots(data) {
            this.touristSpots = data;
            sessionStorage.setItem('map_cache_tourist_spots', JSON.stringify(data));
        },

        clear() {
            this.municipalities = null;
            this.touristSpots = null;
            sessionStorage.removeItem('map_cache_municipalities');
            sessionStorage.removeItem('map_cache_tourist_spots');
        }
    };
})();
