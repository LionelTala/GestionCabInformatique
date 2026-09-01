import { Injectable, signal } from '@angular/core';

@Injectable({
  providedIn: 'root'
})
export class LoadingService {
  private loading = signal(false);
  private requestsCount = 0;

  get isLoading() {
    return this.loading.asReadonly();
  }

  show() {
    this.requestsCount++;
    this.loading.set(true);
  }

  hide() {
    this.requestsCount--;
    if (this.requestsCount <= 0) {
      this.requestsCount = 0;
      this.loading.set(false);
    }
  }
}