// app.config.ts
import { ApplicationConfig, provideZoneChangeDetection } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideHttpClient, withInterceptors, withXsrfConfiguration } from '@angular/common/http';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideToastr } from 'ngx-toastr';

import { routes } from './app.routes';
import { authInterceptor } from './core/interceptors/auth-interceptor';
import { errorInterceptor } from './core/interceptors/error-interceptor';
import { loadingInterceptor } from './core/interceptors/loading-interceptor';
import { xsrfInterceptor } from './core/interceptors/xsrf-interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideZoneChangeDetection({ eventCoalescing: true }),
    provideRouter(routes),

    provideHttpClient(
  withInterceptors([
    xsrfInterceptor,   // ⚠️ en premier, avant authInterceptor
    authInterceptor,
    errorInterceptor,
    loadingInterceptor,
  ])
  // ❌ supprime withXsrfConfiguration, il est inutile pour tes URLs absolues
),

    provideAnimations(),
    provideToastr({
      positionClass: 'toast-top-right',
      timeOut: 5000,
      closeButton: true,
      progressBar: true,
      preventDuplicates: true,
      maxOpened: 3,
      newestOnTop: true,
    }),
  ]
};
