import { Component, signal, ChangeDetectionStrategy } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule, Router } from '@angular/router';
import { ToastrService } from 'ngx-toastr';
import { Auth } from '../../../core/services/auth';

@Component({
  imports: [CommonModule, FormsModule, RouterModule],
  selector: 'app-login',
  styleUrl: './login.css',
  templateUrl: './login.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Login {
  email = '';
  password = '';
  showPassword = signal(false);
  loading = signal(false);
  emailError = signal('');
  passwordError = signal('');
  errorMessage = signal('');

  constructor(
    private auth: Auth,
    private router: Router,
    private toastr: ToastrService
  ) {}

  togglePassword() {
    this.showPassword.update(v => !v);
  }

  validateEmail(): boolean {
    if (!this.email) {
      this.emailError.set("L'email est requis");
      return false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.email)) {
      this.emailError.set('Email invalide');
      return false;
    }
    this.emailError.set('');
    return true;
  }

  validatePassword(): boolean {
    if (!this.password) {
      this.passwordError.set('Le mot de passe est requis');
      return false;
    }
    if (this.password.length < 6) {
      this.passwordError.set('6 caractères minimum');
      return false;
    }
    this.passwordError.set('');
    return true;
  }

  onSubmit() {
    if (!this.validateEmail() || !this.validatePassword()) return;

    this.loading.set(true);
    this.errorMessage.set('');

    this.auth.login(this.email, this.password).subscribe({
      next: () => {
        this.loading.set(false);
        this.toastr.success('Connexion réussie !', 'Bienvenue');
        this.router.navigate(['/dashboard']);
      },
      error: (err: any) => {
        this.loading.set(false);
        const msg = err.error?.error?.message || err.error?.message || 'Identifiants incorrects';
        this.errorMessage.set(msg);
      }
    });
  }
}