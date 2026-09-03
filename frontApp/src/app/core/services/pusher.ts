import { Injectable } from '@angular/core';
import Pusher from 'pusher-js';
import { environment } from '../../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class PusherService {
  private pusher: any;

  constructor() {
    this.init();
  }

  private getXsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
  }

  init() {
    // Auth 100% session Sanctum (guard 'web') : plus besoin de token
    // localStorage ni de header Authorization, seuls le cookie de session
    // et le header X-XSRF-TOKEN comptent.
    this.pusher = new Pusher(environment.pusherKey, {
      cluster: environment.pusherCluster,
      forceTLS: true,
      enabledTransports: ['ws', 'wss'],
      channelAuthorization: {
        transport: 'ajax',
        endpoint: environment.apiUrl + '/broadcasting/auth',
        customHandler: (params: { socketId: string; channelName: string }, callback: (error: any, authData: any) => void) => {
          fetch(environment.apiUrl + '/broadcasting/auth', {
            method: 'POST',
            credentials: 'include',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/x-www-form-urlencoded',
              'X-XSRF-TOKEN': this.getXsrfToken() || '',
            },
            body: new URLSearchParams({
              socket_id: params.socketId,
              channel_name: params.channelName,
            }).toString(),
          })
            .then(res => {
              if (!res.ok) throw new Error('Auth Pusher échouée: ' + res.status);
              return res.json();
            })
            .then(data => callback(null, data))
            .catch(err => callback(err, null));
        },
      },
    });

    this.pusher.connection.bind('connected', () => {
      console.log('✅ Pusher connecté');
    });

    this.pusher.connection.bind('error', (err: any) => {
      console.error('❌ Pusher erreur:', err);
    });
  }

  subscribe(channel: string, event: string, callback: (data: any) => void) {
    const channelInstance = this.pusher.subscribe(channel);
    channelInstance.bind(event, (data: any) => {
      console.log('📢 Événement reçu sur', channel, ':', data);
      callback(data);
    });
  }

  listenNewRegistration(callback: (data: any) => void) {
    const user = JSON.parse(localStorage.getItem('user') || '{}');
    const channel = 'private-user.' + (user?.id || 0);
    console.log('🔔 Abonnement au channel:', channel);
    this.subscribe(channel, 'App\\Events\\NewRegistrationEvent', callback);
  }
}