import './bootstrap';
import Alpine from 'alpinejs';

// Import CSS
import '../css/app.css';

// Setup Alpine.js
window.Alpine = Alpine;

// Alpine Components untuk Games
Alpine.data('gameRoom', () => ({
    players: [],
    isReady: false,
    isHost: false,
    roomCode: '',
    gameType: '',
    status: 'waiting',
    
    init() {
        this.subscribeToRoom();
    },
    
    toggleReady() {
        this.isReady = !this.isReady;
        // Emit ke server via Echo
        if (window.Echo) {
            window.Echo.private(`room.${this.roomCode}`)
                .whisper('player-ready', { isReady: this.isReady });
        }
    },
    
    subscribeToRoom() {
        if (window.Echo && this.roomCode) {
            window.Echo.private(`room.${this.roomCode}`)
                .listen('PlayerJoined', (e) => {
                    this.players = e.players;
                })
                .listen('PlayerLeft', (e) => {
                    this.players = e.players;
                })
                .listen('GameStarted', (e) => {
                    this.status = 'playing';
                    window.location.href = e.gameUrl;
                });
        }
    },
    
    startGame() {
        if (!this.isHost) return;
        fetch(`/api/rooms/${this.roomCode}/start`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        });
    }
}));

Alpine.data('timer', (initialTime = 30) => ({
    time: initialTime,
    interval: null,
    isRunning: false,
    
    get displayTime() {
        const mins = Math.floor(this.time / 60);
        const secs = this.time % 60;
        return mins > 0 ? `${mins}:${secs.toString().padStart(2, '0')}` : secs;
    },
    
    get timerClass() {
        if (this.time <= 5) return 'timer-danger';
        if (this.time <= 10) return 'timer-warning';
        return '';
    },
    
    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        this.interval = setInterval(() => {
            if (this.time > 0) {
                this.time--;
            } else {
                this.stop();
                this.$dispatch('timer-ended');
            }
        }, 1000);
    },
    
    stop() {
        this.isRunning = false;
        clearInterval(this.interval);
    },
    
    reset(newTime = initialTime) {
        this.stop();
        this.time = newTime;
    }
}));

Alpine.data('toast', () => ({
    show: false,
    message: '',
    type: 'info',
    
    showToast(message, type = 'info', duration = 3000) {
        this.message = message;
        this.type = type;
        this.show = true;
        
        setTimeout(() => {
            this.show = false;
        }, duration);
    },
    
    get toastClass() {
        const classes = {
            'info': 'bg-primary-500',
            'success': 'bg-green-500',
            'error': 'bg-red-500',
            'warning': 'bg-yellow-500'
        };
        return classes[this.type] || classes.info;
    }
}));

Alpine.start();

