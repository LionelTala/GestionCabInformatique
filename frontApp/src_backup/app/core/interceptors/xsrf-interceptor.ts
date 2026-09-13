// core/interceptors/xsrf-interceptor.ts
import { HttpInterceptorFn } from '@angular/common/http';

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
  return match ? decodeURIComponent(match[1]) : null;
}

export const xsrfInterceptor: HttpInterceptorFn = (req, next) => {
  const token = getCookie('XSRF-TOKEN');
  const mutating = !['GET', 'HEAD', 'OPTIONS'].includes(req.method);

  const cloned = req.clone({
    withCredentials: true,
    ...(mutating && token ? { setHeaders: { 'X-XSRF-TOKEN': token } } : {}),
  });

  return next(cloned);
};