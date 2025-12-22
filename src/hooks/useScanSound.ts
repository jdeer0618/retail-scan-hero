import { useCallback, useRef } from 'react';

// Generate simple beep sounds using Web Audio API
function createBeep(frequency: number, duration: number, type: OscillatorType = 'sine'): () => void {
  return () => {
    try {
      const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
      const oscillator = audioContext.createOscillator();
      const gainNode = audioContext.createGain();

      oscillator.connect(gainNode);
      gainNode.connect(audioContext.destination);

      oscillator.frequency.value = frequency;
      oscillator.type = type;

      gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + duration);

      oscillator.start(audioContext.currentTime);
      oscillator.stop(audioContext.currentTime + duration);
    } catch (e) {
      console.warn('Audio not available:', e);
    }
  };
}

const playSuccessSound = createBeep(880, 0.15);
const playExceptionSound = () => {
  // Two-tone descending for exception
  try {
    const audioContext = new (window.AudioContext || (window as any).webkitAudioContext)();
    
    const osc1 = audioContext.createOscillator();
    const gain1 = audioContext.createGain();
    osc1.connect(gain1);
    gain1.connect(audioContext.destination);
    osc1.frequency.value = 440;
    osc1.type = 'square';
    gain1.gain.setValueAtTime(0.2, audioContext.currentTime);
    gain1.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
    osc1.start(audioContext.currentTime);
    osc1.stop(audioContext.currentTime + 0.1);

    const osc2 = audioContext.createOscillator();
    const gain2 = audioContext.createGain();
    osc2.connect(gain2);
    gain2.connect(audioContext.destination);
    osc2.frequency.value = 330;
    osc2.type = 'square';
    gain2.gain.setValueAtTime(0.2, audioContext.currentTime + 0.12);
    gain2.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.25);
    osc2.start(audioContext.currentTime + 0.12);
    osc2.stop(audioContext.currentTime + 0.25);
  } catch (e) {
    console.warn('Audio not available:', e);
  }
};

export function useScanSound(enabled: boolean) {
  const lastPlayTime = useRef<number>(0);

  const playSuccess = useCallback(() => {
    if (!enabled) return;
    const now = Date.now();
    if (now - lastPlayTime.current > 100) {
      playSuccessSound();
      lastPlayTime.current = now;
    }
  }, [enabled]);

  const playException = useCallback(() => {
    if (!enabled) return;
    const now = Date.now();
    if (now - lastPlayTime.current > 100) {
      playExceptionSound();
      lastPlayTime.current = now;
    }
  }, [enabled]);

  return { playSuccess, playException };
}
