import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CashMovements } from './cash-movements';

describe('CashMovements', () => {
  let component: CashMovements;
  let fixture: ComponentFixture<CashMovements>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CashMovements],
    }).compileComponents();

    fixture = TestBed.createComponent(CashMovements);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
